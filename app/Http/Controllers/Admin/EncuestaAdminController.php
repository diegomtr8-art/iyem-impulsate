<?php

namespace App\Http\Controllers\Admin;

use App\Exports\EncuestasExport;
use App\Exports\EncuestasReporteExport;
use App\Http\Controllers\Controller;
use App\Mail\EncuestaSatisfaccionMail;
use App\Models\EncuestaPlantilla;
use App\Models\EncuestaRespuesta;
use App\Models\EncuestaSatisfaccion;
use App\Models\Evento;
use App\Models\User;
use App\Services\EncuestaEnvioService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;

class EncuestaAdminController extends Controller
{
    public function index(Request $request)
    {
        $eventoId = $request->get('evento_id');

        $base = EncuestaSatisfaccion::where('es_prueba', false);
        if ($eventoId) {
            $base->where('evento_id', $eventoId);
        }

        $totalEnviadas    = (clone $base)->count();
        $totalRespondidas = (clone $base)->whereNotNull('completada_at')->count();
        $totalPendientes  = $totalEnviadas - $totalRespondidas;
        $tasaRespuesta    = $totalEnviadas > 0 ? round($totalRespondidas * 100 / $totalEnviadas, 1) : 0;

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

        $ultimasRespondidas = EncuestaSatisfaccion::with(['evento', 'user', 'respuestas'])
            ->where('es_prueba', false)
            ->whereNotNull('completada_at')
            ->when($eventoId, fn ($q) => $q->where('evento_id', $eventoId))
            ->orderByDesc('completada_at')
            ->take(10)
            ->get()
            ->map(fn ($e) => [
                'id'            => $e->id,
                'evento'        => $e->evento?->nombre,
                'usuario'       => $e->user?->name,
                'tipo'          => $e->tipo,
                'completada_at' => $e->completada_at->format('d/m/Y H:i'),
                'respuestas'    => $e->respuestas->map(fn ($r) => [
                    'pregunta'  => $r->pregunta,
                    'respuesta' => $r->respuesta,
                ])->values(),
            ]);

        $encuestas = (clone $base)->with(['evento', 'user'])
            ->orderByDesc('created_at')
            ->paginate(50)
            ->through(fn ($e) => [
                'id'            => $e->id,
                'evento'        => $e->evento?->only(['id', 'nombre']),
                'usuario'       => $e->user?->only(['id', 'name', 'email']),
                'tipo'          => $e->tipo,
                'completada'    => $e->completada(),
                'completada_at' => $e->completada_at?->format('d/m/Y H:i'),
                'created_at'    => $e->created_at->format('d/m/Y'),
            ]);

        $eventos = Evento::orderByDesc('created_at')->get(['id', 'nombre']);

        return Inertia::render('Admin/Encuestas/Index', [
            'encuestas'          => $encuestas,
            'eventos'            => $eventos,
            'filtroEvento'       => $eventoId,
            'plantillas'         => EncuestaPlantilla::orderByDesc('activa')->orderBy('nombre')->get(['id', 'nombre', 'activa', 'segmento', 'descripcion']),
            'plantillaActiva'    => EncuestaPlantilla::where('activa', true)->first(['id', 'nombre']),
            'metricas'           => [
                'total_enviadas'    => $totalEnviadas,
                'total_respondidas' => $totalRespondidas,
                'total_pendientes'  => $totalPendientes,
                'tasa_respuesta'    => $tasaRespuesta,
                'escala'            => $metricasEscala,
                'binario'           => $metricasBinario,
            ],
            'ultimasRespondidas' => $ultimasRespondidas,
        ]);
    }

    public function verRespuesta(EncuestaSatisfaccion $encuesta)
    {
        $encuesta->load(['evento', 'user', 'respuestas', 'plantilla']);

        return Inertia::render('Admin/Encuestas/VerRespuesta', [
            'encuesta' => [
                'id'            => $encuesta->id,
                'evento'        => $encuesta->evento?->only(['id', 'nombre']),
                'usuario'       => $encuesta->user?->only(['id', 'name', 'email']),
                'tipo'          => $encuesta->tipo,
                'segmento'      => $encuesta->segmento,
                'es_prueba'     => $encuesta->es_prueba,
                'completada_at' => $encuesta->completada_at?->format('d/m/Y H:i'),
                'created_at'    => $encuesta->created_at->format('d/m/Y H:i'),
                'plantilla'     => $encuesta->plantilla?->only(['id', 'nombre']),
                'respuestas'    => $encuesta->respuestas->map(fn ($r) => [
                    'id'        => $r->id,
                    'pregunta'  => $r->pregunta,
                    'tipo'      => $r->tipo,
                    'respuesta' => $r->respuesta,
                ])->values(),
            ],
        ]);
    }

    public function graficas(Request $request)
    {
        $eventoId    = $request->get('evento_id');
        $plantillaId = $request->get('plantilla_id');
        $segmento    = $request->get('segmento'); // 'comprador' | 'proveedor' | null
        $desde       = $request->get('desde');
        $hasta       = $request->get('hasta');

        // Plantilla activa o la seleccionada
        $plantilla = $this->resolverPlantilla($plantillaId);

        // Base query de encuestas completadas
        $base = EncuestaSatisfaccion::where('es_prueba', false)
            ->whereNotNull('completada_at');

        if ($eventoId) {
            $base->where('evento_id', $eventoId);
        }
        if ($segmento) {
            $base->where('tipo', $segmento);
        }
        if ($desde) {
            $base->whereDate('completada_at', '>=', $desde);
        }
        if ($hasta) {
            $base->whereDate('completada_at', '<=', $hasta);
        }

        $encuestaIds       = (clone $base)->pluck('id');
        $totalRespondidas  = $encuestaIds->count();

        // Totales enviadas (sin filtro completada_at)
        $totalEnviadas = EncuestaSatisfaccion::where('es_prueba', false)
            ->when($eventoId, fn ($q) => $q->where('evento_id', $eventoId))
            ->when($segmento, fn ($q) => $q->where('tipo', $segmento))
            ->count();

        $compradores = (clone $base)->where('tipo', 'comprador')->count();
        $proveedores = (clone $base)->where('tipo', 'proveedor')->count();

        // Agrupar respuestas por pregunta
        $respuestasAgrupadas = $this->agruparRespuestasPorPregunta($encuestaIds);

        // Respondientes individuales (para vista "Por Persona")
        $respondientes = (clone $base)->with(['user', 'respuestas'])
            ->orderBy('tipo')
            ->orderByDesc('completada_at')
            ->get()
            ->map(fn ($e) => [
                'id'         => $e->id,
                'nombre'     => $e->user?->name ?? $e->email_prueba ?? '—',
                'email'      => $e->user?->email ?? $e->email_prueba ?? '—',
                'tipo'       => $e->tipo,
                'fecha'      => $e->completada_at?->format('d/m/Y H:i'),
                'respuestas' => $e->respuestas->map(fn ($r) => [
                    'pregunta'  => $r->pregunta,
                    'respuesta' => $r->respuesta,
                    'tipo'      => $r->tipo,
                ])->values(),
            ]);

        return Inertia::render('Admin/Encuestas/Graficas', [
            'plantilla'  => $plantilla ? [
                'id'        => $plantilla->id,
                'nombre'    => $plantilla->nombre,
                'preguntas' => $plantilla->preguntas,
            ] : null,
            'plantillas' => EncuestaPlantilla::orderByDesc('activa')->get(['id', 'nombre', 'activa']),
            'eventos'    => Evento::orderByDesc('created_at')->get(['id', 'nombre']),
            'filtros'    => compact('eventoId', 'plantillaId', 'segmento', 'desde', 'hasta'),
            'kpis'       => [
                'total_enviadas'    => $totalEnviadas,
                'total_respondidas' => $totalRespondidas,
                'tasa'              => $totalEnviadas > 0 ? round($totalRespondidas * 100 / $totalEnviadas, 1) : 0,
                'compradores'       => $compradores,
                'proveedores'       => $proveedores,
            ],
            'datos'      => $respuestasAgrupadas,
            'respondientes' => $respondientes,
        ]);
    }

    /**
     * Agrupa las respuestas de un conjunto de encuestas por texto de pregunta,
     * calculando conteos/porcentajes por opción, promedio (escala/nps) y
     * lista de textos libres (tipo texto). Reutilizado por graficas(), reportePdf()
     * y el reporte Excel.
     */
    private function agruparRespuestasPorPregunta(\Illuminate\Support\Collection $encuestaIds): \Illuminate\Support\Collection
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

    private function resolverPlantilla(?int $plantillaId): ?EncuestaPlantilla
    {
        return $plantillaId
            ? EncuestaPlantilla::find($plantillaId)
            : EncuestaPlantilla::where('activa', true)->first();
    }

    public function exportar(Request $request)
    {
        $eventoId = $request->get('evento_id');
        $suffix   = $eventoId ? "_evento_{$eventoId}" : '_todos';
        $filename = "encuestas{$suffix}_" . now()->format('Y-m-d') . '.xlsx';

        return Excel::download(new EncuestasExport($eventoId), $filename);
    }

    public function reportePdf(Request $request)
    {
        $plantilla = $this->resolverPlantilla($request->get('plantilla_id'));
        if (!$plantilla) {
            return back()->withErrors(['error' => 'No hay plantilla activa. Activa una primero.']);
        }

        $eventoId = $request->get('evento_id');
        $evento   = $eventoId ? Evento::find($eventoId) : null;

        $base = EncuestaSatisfaccion::where('es_prueba', false)
            ->whereNotNull('completada_at')
            ->when($eventoId, fn ($q) => $q->where('evento_id', $eventoId));

        $totalRespondidas = (clone $base)->count();
        $totalEnviadas    = EncuestaSatisfaccion::where('es_prueba', false)
            ->when($eventoId, fn ($q) => $q->where('evento_id', $eventoId))
            ->count();
        $compradores = (clone $base)->where('tipo', 'comprador')->count();
        $proveedores = (clone $base)->where('tipo', 'proveedor')->count();

        $encuestaIds    = (clone $base)->pluck('id');
        $datosGenerales = $this->agruparRespuestasPorPregunta($encuestaIds)
            ->filter(fn ($d) => $d['tipo'] !== 'texto');

        $respuestasPorPersona = (clone $base)->with(['user', 'respuestas'])
            ->orderBy('tipo')
            ->orderByDesc('completada_at')
            ->get()
            ->map(fn ($e) => [
                'nombre'     => $e->user?->name ?? $e->email_prueba ?? '—',
                'email'      => $e->user?->email ?? $e->email_prueba ?? '—',
                'tipo'       => $e->tipo,
                'fecha'      => $e->completada_at?->format('d/m/Y H:i'),
                'respuestas' => $e->respuestas->map(fn ($r) => [
                    'pregunta'  => $r->pregunta,
                    'respuesta' => $r->respuesta,
                    'tipo'      => $r->tipo,
                ])->values(),
            ]);

        $pdf = Pdf::loadView('exports.encuesta-reporte', [
            'plantilla'            => $plantilla,
            'evento'               => $evento,
            'totalEnviadas'        => $totalEnviadas,
            'totalRespondidas'     => $totalRespondidas,
            'tasa'                 => $totalEnviadas > 0 ? round($totalRespondidas * 100 / $totalEnviadas, 1) : 0,
            'compradores'          => $compradores,
            'proveedores'          => $proveedores,
            'datosGenerales'       => $datosGenerales,
            'respuestasPorPersona' => $respuestasPorPersona,
            'fecha'                => now()->format('d/m/Y H:i'),
        ])->setPaper('a4', 'portrait');

        return $pdf->download('reporte-encuestas-' . now()->format('Y-m-d') . '.pdf');
    }

    public function reporteExcel(Request $request)
    {
        $plantilla = $this->resolverPlantilla($request->get('plantilla_id'));
        if (!$plantilla) {
            return back()->withErrors(['error' => 'No hay plantilla activa. Activa una primero.']);
        }

        $eventoId = $request->get('evento_id');
        $filename = 'encuestas-' . now()->format('Y-m-d') . '.xlsx';

        return Excel::download(new EncuestasReporteExport($plantilla, $eventoId), $filename);
    }

    // ── Plantillas ────────────────────────────────────────────────────────────

    public function plantillas()
    {
        return Inertia::render('Admin/Encuestas/Plantillas', [
            'plantillas' => EncuestaPlantilla::orderByDesc('created_at')->get(),
        ]);
    }

    public function guardarPlantilla(Request $request)
    {
        $data = $request->validate([
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
        ]);

        EncuestaPlantilla::updateOrCreate(
            ['id' => $data['id'] ?? null],
            [
                'nombre'      => $data['nombre'],
                'descripcion' => $data['descripcion'] ?? null,
                'segmento'    => $data['segmento'],
                'preguntas'   => $data['preguntas'],
            ]
        );

        return back()->with('success', 'Plantilla guardada correctamente.');
    }

    public function activarPlantilla(EncuestaPlantilla $plantilla)
    {
        EncuestaPlantilla::query()->update(['activa' => false]);
        $plantilla->update(['activa' => true]);

        return back()->with('success', "Plantilla \"{$plantilla->nombre}\" activada. Se usará en los próximos envíos.");
    }

    public function eliminarPlantilla(EncuestaPlantilla $plantilla)
    {
        if ($plantilla->activa) {
            return back()->withErrors(['error' => 'No puedes eliminar la plantilla activa. Activa otra primero.']);
        }
        $plantilla->delete();

        return back()->with('success', 'Plantilla eliminada.');
    }

    // ── Envíos ────────────────────────────────────────────────────────────────

    public function enviarParaEvento(Request $request)
    {
        $request->validate([
            'evento_id'    => 'required|exists:eventos,id',
            'plantilla_id' => 'nullable|exists:encuesta_plantillas,id',
            'segmento'     => 'required|in:todos,proveedores,compradores,proveedores_evento,compradores_evento',
        ]);

        $evento    = Evento::findOrFail($request->evento_id);
        $plantilla = $this->resolverPlantilla($request->plantilla_id);

        if (!$plantilla) {
            return back()->withErrors(['error' => 'No hay plantilla activa. Activa una plantilla primero.']);
        }

        $enviados = app(EncuestaEnvioService::class)
            ->enviarParaEvento($evento, $plantilla, $request->segmento);

        return back()->with('success', "Encuestas enviadas a {$enviados} participante(s).");
    }

    public function enviarPrueba(Request $request)
    {
        $request->validate([
            'email'        => 'required|email',
            'plantilla_id' => 'nullable|exists:encuesta_plantillas,id',
        ]);

        $plantilla = $this->resolverPlantilla($request->plantilla_id);

        $encuesta = EncuestaSatisfaccion::create([
            'evento_id'             => null,
            'user_id'               => null,
            'tipo'                  => 'comprador',
            'token'                 => Str::random(40),
            'encuesta_plantilla_id' => $plantilla?->id,
            'es_prueba'             => true,
            'email_prueba'          => $request->email,
        ]);

        Mail::to($request->email)->send(new EncuestaSatisfaccionMail($encuesta->load('plantilla'), esPrueba: true));

        return back()->with('success', "Encuesta de prueba enviada a {$request->email}.");
    }

    public function enviarRecordatorio(Request $request)
    {
        $request->validate([
            'evento_id' => 'required|exists:eventos,id',
        ]);

        $pendientes = EncuestaSatisfaccion::where('evento_id', $request->evento_id)
            ->where('es_prueba', false)
            ->whereNull('completada_at')
            ->get();

        if ($pendientes->isEmpty()) {
            return back()->with('info', 'Todos los participantes ya respondieron la encuesta. 🎉');
        }

        foreach ($pendientes as $encuesta) {
            \App\Jobs\SendRecordatorioJob::dispatch($encuesta->id)
                ->onQueue('encuestas');
        }

        return back()->with('success', "Recordatorio enviado a {$pendientes->count()} persona(s) que no han contestado.");
    }

    public function participantes(Request $request)
    {
        $request->validate(['evento_id' => 'required|exists:eventos,id']);

        $participantes = DB::table('evento_usuario')
            ->join('users', 'users.id', '=', 'evento_usuario.user_id')
            ->leftJoin('restauranteros', 'restauranteros.user_id', '=', 'users.id')
            ->where('evento_usuario.evento_id', $request->evento_id)
            ->where('evento_usuario.estado', 'aprobado')
            ->select([
                'users.id',
                'users.name',
                'users.email',
                'evento_usuario.tipo',
                DB::raw("COALESCE(restauranteros.nombre_restaurante, users.name) as nombre_negocio"),
            ])
            ->orderBy('evento_usuario.tipo')
            ->orderBy('users.name')
            ->get()
            ->map(fn ($p) => [
                'user_id'        => $p->id,
                'name'           => $p->name,
                'email'          => $p->email,
                'tipo'           => $p->tipo,
                'nombre_negocio' => $p->nombre_negocio,
                'ya_enviada'     => EncuestaSatisfaccion::where('evento_id', $request->evento_id)
                    ->where('user_id', $p->id)
                    ->exists(),
            ]);

        return response()->json($participantes);
    }

    public function enviarSeleccion(Request $request)
    {
        $request->validate([
            'evento_id'    => 'required|exists:eventos,id',
            'plantilla_id' => 'nullable|exists:encuesta_plantillas,id',
            'user_ids'     => 'required|array|min:1',
            'user_ids.*'   => 'exists:users,id',
        ]);

        $evento    = Evento::findOrFail($request->evento_id);
        $plantilla = $this->resolverPlantilla($request->plantilla_id);

        if (!$plantilla) {
            return back()->withErrors(['error' => 'No hay plantilla activa. Activa una primero.']);
        }

        $participantes = DB::table('evento_usuario')
            ->where('evento_id', $evento->id)
            ->where('estado', 'aprobado')
            ->whereIn('user_id', $request->user_ids)
            ->get(['user_id', 'tipo']);

        $enviados = 0;
        foreach ($participantes as $p) {
            $existe = EncuestaSatisfaccion::where('evento_id', $evento->id)
                ->where('user_id', $p->user_id)
                ->where('tipo', $p->tipo)
                ->exists();

            if (!$existe) {
                $encuesta = EncuestaSatisfaccion::create([
                    'evento_id'             => $evento->id,
                    'user_id'               => $p->user_id,
                    'tipo'                  => $p->tipo,
                    'segmento'              => $p->tipo === 'proveedor' ? 'proveedores' : 'compradores',
                    'token'                 => Str::random(40),
                    'encuesta_plantilla_id' => $plantilla->id,
                ]);

                $user = User::find($p->user_id);
                if ($user?->email) {
                    Mail::to($user->email)
                        ->send(new EncuestaSatisfaccionMail($encuesta->load(['evento', 'user', 'plantilla'])));
                    $enviados++;
                }
            }
        }

        return back()->with('success', "Encuesta enviada a {$enviados} participante(s) seleccionado(s).");
    }
}
