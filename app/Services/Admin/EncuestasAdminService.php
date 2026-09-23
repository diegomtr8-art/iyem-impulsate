<?php

namespace App\Services\Admin;

use App\Exceptions\ReglaDeNegocioException;
use App\Exports\EncuestasExport;
use App\Exports\EncuestasReporteExport;
use App\Jobs\SendRecordatorioJob;
use App\Mail\EncuestaRecordatorioMail;
use App\Mail\EncuestaSatisfaccionMail;
use App\Models\EncuestaPlantilla;
use App\Models\EncuestaRespuesta;
use App\Models\EncuestaSatisfaccion;
use App\Models\Evento;
use App\Models\User;
use App\Services\EncuestaEnvioService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Encuestas de satisfaccion desde el panel admin: resumen y lista, detalle de
 * una respuesta, datos agregados para las graficas, reportes (PDF/Excel),
 * plantillas de preguntas y envios (evento, seleccion, prueba, recordatorios).
 * Lo usan Admin\EncuestaAdminController (web) y la API v1.
 */
class EncuestasAdminService
{
    public const SEGMENTOS_ENVIO = ['todos', 'proveedores', 'compradores', 'proveedores_evento', 'compradores_evento'];

    public const TIPOS_PREGUNTA = ['escala', 'texto', 'binario', 'opciones', 'multiple', 'nps'];

    public const REGLAS_PLANTILLA = [
        'id'                                 => 'nullable|exists:encuesta_plantillas,id',
        'nombre'                              => 'required|string|max:150',
        'descripcion'                         => 'nullable|string|max:500',
        'segmento'                            => 'required|in:todos,proveedores,compradores,proveedores_evento,compradores_evento',
        'preguntas'                           => 'required|array|min:1',
        'preguntas.*.id'                      => 'required|string|max:80',
        'preguntas.*.tipo'                    => 'required|in:escala,texto,binario,opciones,multiple,nps',
        'preguntas.*.texto'                   => 'required|string|max:500',
        'preguntas.*.requerida'               => 'nullable|boolean',
        'preguntas.*.opciones'                => 'nullable|array',
        'preguntas.*.opciones.*'              => 'nullable|string|max:200',
        'preguntas.*.escala_min'              => 'nullable|integer|min:0|max:10',
        'preguntas.*.escala_max'              => 'nullable|integer|min:1|max:10',
        'preguntas.*.condicion'               => 'nullable|array',
        'preguntas.*.condicion.pregunta_id'   => 'nullable|string',
        'preguntas.*.condicion.operador'      => 'nullable|in:igual,diferente,mayor,menor',
        'preguntas.*.condicion.valor'         => 'nullable|string',
    ];

    public function __construct(private EncuestaEnvioService $envio) {}

    /* ─────────────── resumen y lista ─────────────── */

    private function base(?int $eventoId): Builder
    {
        return EncuestaSatisfaccion::where('es_prueba', false)
            ->when($eventoId, fn ($q) => $q->where('evento_id', $eventoId));
    }

    /** KPIs de /admin/encuestas: enviadas, respondidas, promedio por pregunta de escala y % de "Sí". */
    public function metricas(?int $eventoId): array
    {
        $base = $this->base($eventoId);

        $totalEnviadas    = (clone $base)->count();
        $totalRespondidas = (clone $base)->whereNotNull('completada_at')->count();

        $respuestasQuery = EncuestaRespuesta::query()
            ->whereHas('encuesta', function ($q) use ($eventoId) {
                $q->where('es_prueba', false);
                if ($eventoId) {
                    $q->where('evento_id', $eventoId);
                }
            });

        $metricasEscala = (clone $respuestasQuery)->where('tipo', 'escala')
            ->selectRaw('pregunta, AVG(CAST(respuesta AS DECIMAL(3,1))) as promedio, COUNT(*) as total')
            ->groupBy('pregunta')
            ->get();

        $metricasBinario = (clone $respuestasQuery)->where('tipo', 'binario')
            ->selectRaw("pregunta, SUM(CASE WHEN respuesta = 'Sí' THEN 1 ELSE 0 END) as positivas, COUNT(*) as total")
            ->groupBy('pregunta')
            ->get()
            ->map(fn ($r) => [
                'pregunta'      => $r->pregunta,
                'porcentaje_si' => $r->total > 0 ? round($r->positivas * 100 / $r->total, 1) : 0,
            ]);

        return [
            'total_enviadas'    => $totalEnviadas,
            'total_respondidas' => $totalRespondidas,
            'total_pendientes'  => $totalEnviadas - $totalRespondidas,
            'tasa_respuesta'    => $totalEnviadas > 0 ? round($totalRespondidas * 100 / $totalEnviadas, 1) : 0,
            'escala'            => $metricasEscala,
            'binario'           => $metricasBinario,
        ];
    }

    /** Las ultimas N respondidas, con evento, usuario y respuestas. */
    public function ultimasRespondidas(?int $eventoId, int $cuantas = 10): Collection
    {
        return EncuestaSatisfaccion::with(['evento', 'user', 'respuestas'])
            ->where('es_prueba', false)
            ->whereNotNull('completada_at')
            ->when($eventoId, fn ($q) => $q->where('evento_id', $eventoId))
            ->orderByDesc('completada_at')
            ->take($cuantas)
            ->get();
    }

    /**
     * Lista paginada de encuestas enviadas (sin las de prueba). $extra solo lo
     * manda la API: estado (respondida|pendiente), tipo y search.
     */
    public function listar(?int $eventoId, int $porPagina = 50, array $extra = []): LengthAwarePaginator
    {
        $q = $this->base($eventoId)->with(['evento', 'user'])->orderByDesc('created_at');

        match ($extra['estado'] ?? null) {
            'respondida' => $q->whereNotNull('completada_at'),
            'pendiente'  => $q->whereNull('completada_at'),
            default      => null,
        };
        if (filled($extra['tipo'] ?? null)) {
            $q->where('tipo', $extra['tipo']);
        }
        if (filled($extra['search'] ?? null)) {
            $s = $extra['search'];
            $q->whereHas('user', fn ($u) => $u->where('name', 'like', "%{$s}%")->orWhere('email', 'like', "%{$s}%"));
        }

        return $q->paginate($porPagina)->withQueryString();
    }

    public function plantillasResumen(): Collection
    {
        return EncuestaPlantilla::orderByDesc('activa')->orderBy('nombre')->get(['id', 'nombre', 'activa', 'segmento', 'descripcion']);
    }

    public function plantillaActiva(): ?EncuestaPlantilla
    {
        return EncuestaPlantilla::where('activa', true)->first();
    }

    public function detalle(EncuestaSatisfaccion $encuesta): EncuestaSatisfaccion
    {
        return $encuesta->load(['evento', 'user', 'respuestas', 'plantilla']);
    }

    /* ─────────────── graficas ─────────────── */

    /**
     * Datos de /admin/encuestas/graficas. Filtros: evento_id, plantilla_id,
     * segmento (comprador|proveedor), desde, hasta (Y-m-d), canirac (si|no).
     *
     * @return array{plantilla: ?EncuestaPlantilla, kpis: array, datos: Collection, respondientes: Collection}
     */
    public function graficas(array $f): array
    {
        $eventoId = $f['evento_id'] ?? null;
        $segmento = $f['segmento'] ?? null;

        $plantilla = $this->resolverPlantilla(isset($f['plantilla_id']) && $f['plantilla_id'] !== '' ? (int) $f['plantilla_id'] : null);

        $base = EncuestaSatisfaccion::where('es_prueba', false)->whereNotNull('completada_at');
        if ($eventoId) {
            $base->where('evento_id', $eventoId);
        }
        $this->aplicarFiltroSegmento($base, $segmento);
        if ($f['desde'] ?? null) {
            $base->whereDate('completada_at', '>=', $f['desde']);
        }
        if ($f['hasta'] ?? null) {
            $base->whereDate('completada_at', '<=', $f['hasta']);
        }
        $this->aplicarFiltroCanirac($base, $f['canirac'] ?? null);

        $encuestaIds      = (clone $base)->pluck('id');
        $totalRespondidas = $encuestaIds->count();

        // Totales enviadas (sin filtro completada_at)
        $totalEnviadasQuery = EncuestaSatisfaccion::where('es_prueba', false)
            ->when($eventoId, fn ($q) => $q->where('evento_id', $eventoId));
        $this->aplicarFiltroSegmento($totalEnviadasQuery, $segmento);
        $totalEnviadas = $totalEnviadasQuery->count();

        // Fuente de verdad: rol Spatie del usuario, no el campo tipo (puede estar
        // desactualizado en registros legacy) — evita descuadres con Correo Masivo.
        [$compradores, $proveedores] = $this->contarPorRol($base);
        $caniracCount = (clone $base)->whereHas('user', fn ($q) => $q->where('camara_asociacion', 'CANIRAC'))->count();

        return [
            'plantilla'     => $plantilla,
            'kpis'          => [
                'total_enviadas'    => $totalEnviadas,
                'total_respondidas' => $totalRespondidas,
                'tasa'              => $totalEnviadas > 0 ? round($totalRespondidas * 100 / $totalEnviadas, 1) : 0,
                'compradores'       => $compradores,
                'proveedores'       => $proveedores,
                'canirac'           => $caniracCount,
            ],
            'datos'         => $this->agruparRespuestasPorPregunta($encuestaIds),
            'respondientes' => $this->respondientes($base, conId: true),
        ];
    }

    /**
     * Agrupa las respuestas de un conjunto de encuestas por texto de pregunta,
     * calculando conteos/porcentajes por opción, promedio (escala/nps) y
     * lista de textos libres (tipo texto). Lo usan las graficas y el reporte PDF.
     */
    public function agruparRespuestasPorPregunta(Collection $encuestaIds): Collection
    {
        return EncuestaRespuesta::whereIn('encuesta_satisfaccion_id', $encuestaIds)
            ->selectRaw('pregunta, tipo, respuesta, COUNT(*) as total')
            ->groupBy('pregunta', 'tipo', 'respuesta')
            ->orderBy('pregunta')
            ->get()
            ->groupBy('pregunta')
            ->map(function ($grupo) {
                $tipo      = $grupo->first()->tipo;
                $totalResp = $grupo->sum('total');
                $opciones  = $grupo->mapWithKeys(fn ($r) => [
                    $r->respuesta => [
                        'count'      => $r->total,
                        'porcentaje' => $totalResp > 0 ? round($r->total * 100 / $totalResp, 1) : 0,
                    ],
                ]);

                $promedio = null;
                if (in_array($tipo, ['escala', 'nps'])) {
                    $promedio = round(
                        $grupo->sum(fn ($r) => (float) $r->respuesta * $r->total) / max($totalResp, 1),
                        1
                    );
                }

                return [
                    'tipo'     => $tipo,
                    'total'    => $totalResp,
                    'opciones' => $opciones,
                    'promedio' => $promedio,
                    // Para tipo 'texto', lista de respuestas individuales
                    'textos'   => $tipo === 'texto'
                        ? $grupo->pluck('respuesta')->filter()->values()
                        : [],
                ];
            });
    }

    public function resolverPlantilla(?int $plantillaId): ?EncuestaPlantilla
    {
        return $plantillaId
            ? EncuestaPlantilla::find($plantillaId)
            : EncuestaPlantilla::where('activa', true)->first();
    }

    /** La plantilla del reporte o la activa; sin ninguna, regla de negocio. */
    private function plantillaParaReporte(?int $plantillaId): EncuestaPlantilla
    {
        return $this->resolverPlantilla($plantillaId)
            ?? throw new ReglaDeNegocioException('No hay plantilla activa. Activa una primero.');
    }

    /**
     * Filtra por segmento comprador/proveedor según el rol Spatie del usuario
     * (fuente de verdad), no el campo tipo almacenado.
     */
    private function aplicarFiltroSegmento($query, ?string $segmento): void
    {
        if ($segmento === 'comprador') {
            $query->whereHas('user.roles', fn ($q) => $q->where('name', 'cliente'))
                ->whereDoesntHave('user.roles', fn ($q) => $q->where('name', 'admin'));
        } elseif ($segmento === 'proveedor') {
            $query->whereHas('user.roles', fn ($q) => $q->where('name', 'restaurantero'));
        }
    }

    private function aplicarFiltroCanirac($query, ?string $canirac): void
    {
        if ($canirac === 'si') {
            $query->whereHas('user', fn ($u) => $u->where('camara_asociacion', 'CANIRAC'));
        } elseif ($canirac === 'no') {
            $query->whereHas('user', fn ($u) => $u->where(
                fn ($u2) => $u2->whereNull('camara_asociacion')->orWhere('camara_asociacion', '!=', 'CANIRAC')
            ));
        }
    }

    private function contarPorRol(Builder $base): array
    {
        return [
            (clone $base)->whereHas('user.roles', fn ($q) => $q->where('name', 'cliente'))
                ->whereDoesntHave('user.roles', fn ($q) => $q->where('name', 'admin'))
                ->count(),
            (clone $base)->whereHas('user.roles', fn ($q) => $q->where('name', 'restaurantero'))->count(),
        ];
    }

    private function respondientes(Builder $base, bool $conId): Collection
    {
        return (clone $base)->with(['user.roles', 'respuestas'])
            ->orderBy('tipo')
            ->orderByDesc('completada_at')
            ->get()
            ->map(fn ($e) => array_merge($conId ? ['id' => $e->id] : [], [
                'nombre'     => $e->user?->name ?? $e->email_prueba ?? '—',
                'email'      => $e->user?->email ?? $e->email_prueba ?? '—',
                'tipo'       => $this->tipoPorRol($e),
                'fecha'      => $e->completada_at?->format('d/m/Y H:i'),
                'respuestas' => $e->respuestas->map(fn ($r) => [
                    'pregunta'  => $r->pregunta,
                    'respuesta' => $r->respuesta,
                    'tipo'      => $r->tipo,
                ])->values(),
            ]));
    }

    private function tipoPorRol(EncuestaSatisfaccion $encuesta): ?string
    {
        if (!$encuesta->user) {
            return $encuesta->tipo;
        }

        if ($encuesta->user->hasRole('restaurantero')) {
            return 'proveedor';
        }

        if ($encuesta->user->hasRole('cliente') && !$encuesta->user->hasRole('admin')) {
            return 'comprador';
        }

        return $encuesta->tipo;
    }

    /* ─────────────── reportes ─────────────── */

    /** Excel plano de encuestas enviadas: [export, nombre de archivo]. */
    public function exportEncuestas(?int $eventoId): array
    {
        $suffix = $eventoId ? "_evento_{$eventoId}" : '_todos';

        return [new EncuestasExport($eventoId), "encuestas{$suffix}_" . now()->format('Y-m-d') . '.xlsx'];
    }

    /** Excel del reporte (resumen + detalle) de la plantilla: [export, nombre]. */
    public function exportReporte(?int $plantillaId, ?int $eventoId, ?string $canirac): array
    {
        $plantilla = $this->plantillaParaReporte($plantillaId);

        return [new EncuestasReporteExport($plantilla, $eventoId, $canirac), 'encuestas-' . now()->format('Y-m-d') . '.xlsx'];
    }

    /** PDF del reporte: [\Barryvdh\DomPDF\PDF, nombre]. */
    public function reportePdf(?int $plantillaId, ?int $eventoId, ?string $canirac): array
    {
        $plantilla = $this->plantillaParaReporte($plantillaId);
        $evento    = $eventoId ? Evento::find($eventoId) : null;

        $base = EncuestaSatisfaccion::where('es_prueba', false)
            ->whereNotNull('completada_at')
            ->when($eventoId, fn ($q) => $q->where('evento_id', $eventoId));
        $this->aplicarFiltroCanirac($base, $canirac);

        $totalRespondidas = (clone $base)->count();
        $totalEnviadas    = $this->base($eventoId)->count();
        [$compradores, $proveedores] = $this->contarPorRol($base);

        $datosGenerales = $this->agruparRespuestasPorPregunta((clone $base)->pluck('id'))
            ->filter(fn ($d) => $d['tipo'] !== 'texto');

        $pdf = Pdf::loadView('exports.encuesta-reporte', [
            'plantilla'            => $plantilla,
            'evento'               => $evento,
            'totalEnviadas'        => $totalEnviadas,
            'totalRespondidas'     => $totalRespondidas,
            'tasa'                 => $totalEnviadas > 0 ? round($totalRespondidas * 100 / $totalEnviadas, 1) : 0,
            'compradores'          => $compradores,
            'proveedores'          => $proveedores,
            'datosGenerales'       => $datosGenerales,
            'respuestasPorPersona' => $this->respondientes($base, conId: false),
            'fecha'                => now()->format('d/m/Y H:i'),
        ])->setPaper('a4', 'portrait');

        return [$pdf, 'reporte-encuestas-' . now()->format('Y-m-d') . '.pdf'];
    }

    /* ─────────────── plantillas ─────────────── */

    public function plantillas(): Collection
    {
        return EncuestaPlantilla::orderByDesc('created_at')->get();
    }

    /** Crea (sin id) o reemplaza (con id) una plantilla. No cambia cual esta activa. */
    public function guardarPlantilla(array $data): EncuestaPlantilla
    {
        return EncuestaPlantilla::updateOrCreate(
            ['id' => $data['id'] ?? null],
            [
                'nombre'      => $data['nombre'],
                'descripcion' => $data['descripcion'] ?? null,
                'segmento'    => $data['segmento'],
                'preguntas'   => $data['preguntas'],
            ]
        );
    }

    /** Solo una activa: la que se usa en los proximos envios. */
    public function activarPlantilla(EncuestaPlantilla $plantilla): void
    {
        EncuestaPlantilla::query()->update(['activa' => false]);
        $plantilla->update(['activa' => true]);
    }

    public function eliminarPlantilla(EncuestaPlantilla $plantilla): void
    {
        if ($plantilla->activa) {
            throw new ReglaDeNegocioException('No puedes eliminar la plantilla activa. Activa otra primero.');
        }
        $plantilla->delete();
    }

    /* ─────────────── envios ─────────────── */

    /** Envia a los aprobados del evento del segmento (omite a quien ya la tiene). Devuelve cuantos correos. */
    public function enviarParaEvento(int $eventoId, ?int $plantillaId, string $segmento): int
    {
        $evento    = Evento::findOrFail($eventoId);
        $plantilla = $this->resolverPlantilla($plantillaId)
            ?? throw new ReglaDeNegocioException('No hay plantilla activa. Activa una plantilla primero.');

        return $this->envio->enviarParaEvento($evento, $plantilla, $segmento);
    }

    /** Encuesta de PRUEBA (no cuenta en metricas) a un correo cualquiera. */
    public function enviarPrueba(string $email, ?int $plantillaId): EncuestaSatisfaccion
    {
        $encuesta = $this->encuestaDePrueba($email, 'comprador', $plantillaId);
        Mail::to($email)->send(new EncuestaSatisfaccionMail($encuesta->load('plantilla'), esPrueba: true));

        return $encuesta;
    }

    /** Correo de RECORDATORIO de prueba (crea una encuesta de prueba tipo proveedor). */
    public function enviarRecordatorioPrueba(string $email, ?int $plantillaId): EncuestaSatisfaccion
    {
        $encuesta = $this->encuestaDePrueba($email, 'proveedor', $plantillaId);
        Mail::to($email)->send(new EncuestaRecordatorioMail($encuesta->load('plantilla')));

        return $encuesta;
    }

    private function encuestaDePrueba(string $email, string $tipo, ?int $plantillaId): EncuestaSatisfaccion
    {
        return EncuestaSatisfaccion::create([
            'evento_id'             => null,
            'user_id'               => null,
            'tipo'                  => $tipo,
            'token'                 => Str::random(40),
            'encuesta_plantilla_id' => $this->resolverPlantilla($plantillaId)?->id,
            'es_prueba'             => true,
            'email_prueba'          => $email,
        ]);
    }

    /**
     * Encola un recordatorio (cola 'encuestas') por cada encuesta pendiente del
     * evento. Devuelve el mismo resumen que la web muestra en el modal.
     */
    public function enviarRecordatorio(int $eventoId): array
    {
        $pendientes = EncuestaSatisfaccion::with('user')
            ->where('evento_id', $eventoId)
            ->where('es_prueba', false)
            ->whereNull('completada_at')
            ->get();

        $yaContestaron = EncuestaSatisfaccion::where('evento_id', $eventoId)
            ->where('es_prueba', false)
            ->whereNotNull('completada_at')
            ->count();

        if ($pendientes->isEmpty()) {
            return [
                'enviados'       => 0,
                'ya_contestaron' => $yaContestaron,
                'personas'       => [],
                'mensaje'        => '¡Todos han contestado!',
            ];
        }

        $personas = [];
        foreach ($pendientes as $encuesta) {
            SendRecordatorioJob::dispatch($encuesta->id)->onQueue('encuestas');
            $personas[] = [
                'nombre' => $encuesta->user?->name ?? $encuesta->email_prueba ?? '—',
                'email'  => $encuesta->user?->email ?? $encuesta->email_prueba ?? '—',
                'tipo'   => $encuesta->tipo,
            ];
        }

        return [
            'enviados'       => $pendientes->count(),
            'ya_contestaron' => $yaContestaron,
            'personas'       => $personas,
            'mensaje'        => "Recordatorio enviado a {$pendientes->count()} persona(s) pendiente(s).",
        ];
    }

    /** Borra la encuesta y sus respuestas (la persona puede volver a recibirla). Devuelve el nombre. */
    public function eliminarRespuestas(EncuestaSatisfaccion $encuesta): string
    {
        $nombre = $encuesta->user?->name ?? $encuesta->email_prueba ?? "ID {$encuesta->id}";

        $encuesta->respuestas()->delete();
        $encuesta->delete();

        return $nombre;
    }

    /** Aprobados del evento (comprador/proveedor por rol) y si ya se les envio. */
    public function participantes(int $eventoId): Collection
    {
        $filas = DB::table('evento_usuario')
            ->join('users', 'users.id', '=', 'evento_usuario.user_id')
            ->leftJoin('restauranteros', 'restauranteros.user_id', '=', 'users.id')
            ->where('evento_usuario.evento_id', $eventoId)
            ->where('evento_usuario.estado', 'aprobado')
            ->select([
                'users.id',
                'users.name',
                'users.email',
                DB::raw('COALESCE(restauranteros.nombre_restaurante, users.name) as nombre_negocio'),
            ])
            ->orderBy('users.name')
            ->get();

        // Fuente de verdad: rol Spatie del usuario, no evento_usuario.tipo
        // (evita descuadres con Correo Masivo).
        $usuariosPorId = User::whereIn('id', $filas->pluck('id'))
            ->with('roles')
            ->get()
            ->keyBy('id');

        return $filas
            ->map(function ($p) use ($usuariosPorId, $eventoId) {
                $tipo = $this->tipoDeUsuario($usuariosPorId->get($p->id));

                if (!$tipo) {
                    return null;
                }

                return [
                    'user_id'        => $p->id,
                    'name'           => $p->name,
                    'email'          => $p->email,
                    'tipo'           => $tipo,
                    'nombre_negocio' => $p->nombre_negocio,
                    'ya_enviada'     => EncuestaSatisfaccion::where('evento_id', $eventoId)
                        ->where('user_id', $p->id)
                        ->exists(),
                ];
            })
            ->filter()
            ->sortBy([['tipo', 'asc'], ['name', 'asc']])
            ->values();
    }

    /** Envia a los usuarios elegidos que esten aprobados en el evento y aun no la tengan. */
    public function enviarSeleccion(int $eventoId, ?int $plantillaId, array $userIds): int
    {
        $evento    = Evento::findOrFail($eventoId);
        $plantilla = $this->resolverPlantilla($plantillaId)
            ?? throw new ReglaDeNegocioException('No hay plantilla activa. Activa una primero.');

        $aprobados = DB::table('evento_usuario')
            ->where('evento_id', $evento->id)
            ->where('estado', 'aprobado')
            ->whereIn('user_id', $userIds)
            ->pluck('user_id');

        // Fuente de verdad: rol Spatie del usuario, no evento_usuario.tipo.
        $usuarios = User::whereIn('id', $aprobados)->with('roles')->get();

        $enviados = 0;
        foreach ($usuarios as $user) {
            $tipo = $this->tipoDeUsuario($user);

            if (!$tipo) {
                continue;
            }

            $existe = EncuestaSatisfaccion::where('evento_id', $evento->id)
                ->where('user_id', $user->id)
                ->where('tipo', $tipo)
                ->exists();

            if (!$existe) {
                $encuesta = EncuestaSatisfaccion::create([
                    'evento_id'             => $evento->id,
                    'user_id'               => $user->id,
                    'tipo'                  => $tipo,
                    'segmento'              => $tipo === 'proveedor' ? 'proveedores' : 'compradores',
                    'token'                 => Str::random(40),
                    'encuesta_plantilla_id' => $plantilla->id,
                ]);

                if ($user->email) {
                    Mail::to($user->email)
                        ->send(new EncuestaSatisfaccionMail($encuesta->load(['evento', 'user', 'plantilla'])));
                    $enviados++;
                }
            }
        }

        return $enviados;
    }

    private function tipoDeUsuario(?User $user): ?string
    {
        return $user?->hasRole('restaurantero')
            ? 'proveedor'
            : ($user?->hasRole('cliente') && !$user->hasRole('admin') ? 'comprador' : null);
    }
}
