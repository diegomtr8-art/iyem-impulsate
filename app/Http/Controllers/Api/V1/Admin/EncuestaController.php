<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\AdminEncuestaResource;
use App\Models\EncuestaSatisfaccion;
use App\Services\Admin\EncuestasAdminService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Encuestas de satisfaccion desde el panel admin (web: /admin/encuestas).
 * EncuestasAdminService. Los reportes PDF/Excel salen por
 * POST /admin/exportaciones/{tipo} (URL firmada).
 */
class EncuestaController extends Controller
{
    public function __construct(private EncuestasAdminService $encuestas) {}

    /** KPIs, plantilla activa y ultimas respondidas (cabecera de /admin/encuestas). */
    public function resumen(Request $request)
    {
        Gate::authorize('administrar', EncuestaSatisfaccion::class);
        $f = $request->validate(['evento_id' => ['nullable', 'integer']]);
        $eventoId = isset($f['evento_id']) ? (int) $f['evento_id'] : null;

        $m = $this->encuestas->metricas($eventoId);
        $activa = $this->encuestas->plantillaActiva();

        return response()->json(['data' => [
            'metricas' => [
                'total_enviadas'    => $m['total_enviadas'],
                'total_respondidas' => $m['total_respondidas'],
                'total_pendientes'  => $m['total_pendientes'],
                'tasa_respuesta'    => (float) $m['tasa_respuesta'],
                'escala'            => $m['escala']->map(fn ($r) => [
                    'pregunta' => $r->pregunta,
                    'promedio' => round((float) $r->promedio, 2),
                    'total'    => (int) $r->total,
                ])->values(),
                'binario'           => $m['binario']->map(fn ($r) => [
                    'pregunta'      => $r['pregunta'],
                    'porcentaje_si' => (float) $r['porcentaje_si'],
                ])->values(),
            ],
            'plantilla_activa'    => $activa ? ['id' => $activa->id, 'nombre' => $activa->nombre] : null,
            'ultimas_respondidas' => AdminEncuestaResource::collection($this->encuestas->ultimasRespondidas($eventoId))->resolve($request),
        ]]);
    }

    public function index(Request $request)
    {
        Gate::authorize('administrar', EncuestaSatisfaccion::class);
        $f = $request->validate([
            'evento_id' => ['nullable', 'integer'],
            'estado'    => ['nullable', Rule::in(['respondida', 'pendiente'])],
            'tipo'      => ['nullable', Rule::in(['comprador', 'proveedor'])],
            'search'    => ['nullable', 'string', 'max:100'],
            'per_page'  => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $pag = $this->encuestas->listar(isset($f['evento_id']) ? (int) $f['evento_id'] : null, (int) ($f['per_page'] ?? 50), $f);

        return AdminEncuestaResource::collection($pag);
    }

    /** Datos agregados para las graficas (sin imagenes): KPIs, por pregunta y por persona. */
    public function graficas(Request $request)
    {
        Gate::authorize('administrar', EncuestaSatisfaccion::class);
        $f = $request->validate([
            'evento_id'    => ['nullable', 'integer'],
            'plantilla_id' => ['nullable', 'integer'],
            'segmento'     => ['nullable', Rule::in(['comprador', 'proveedor'])],
            'desde'        => ['nullable', 'date_format:Y-m-d'],
            'hasta'        => ['nullable', 'date_format:Y-m-d'],
            'canirac'      => ['nullable', Rule::in(['si', 'no'])],
        ]);

        $g = $this->encuestas->graficas($f);

        return response()->json(['data' => [
            'plantilla'     => $g['plantilla'] ? [
                'id'        => $g['plantilla']->id,
                'nombre'    => $g['plantilla']->nombre,
                'preguntas' => array_values($g['plantilla']->preguntas ?? []),
            ] : null,
            'kpis'          => array_merge($g['kpis'], ['tasa' => (float) $g['kpis']['tasa']]),
            'preguntas'     => $g['datos']->map(fn ($d, $pregunta) => [
                'pregunta' => (string) $pregunta,
                'tipo'     => $d['tipo'],
                'total'    => (int) $d['total'],
                'promedio' => $d['promedio'],
                'opciones' => collect($d['opciones'])->map(fn ($o, $valor) => [
                    'valor'      => (string) $valor,
                    'count'      => (int) $o['count'],
                    'porcentaje' => (float) $o['porcentaje'],
                ])->values(),
                'textos'   => collect($d['textos'])->values(),
            ])->values(),
            'respondientes' => $g['respondientes']->values(),
        ], 'meta' => ['filtros' => $f]]);
    }

    public function show(EncuestaSatisfaccion $encuesta)
    {
        Gate::authorize('administrar', $encuesta);

        return new AdminEncuestaResource($this->encuestas->detalle($encuesta));
    }

    /** Borra la encuesta y sus respuestas (web: "Eliminar respuestas"). */
    public function destroy(EncuestaSatisfaccion $encuesta)
    {
        Gate::authorize('administrar', $encuesta);
        $nombre = $this->encuestas->eliminarRespuestas($encuesta);

        return response()->json(['data' => null, 'message' => "Respuestas de {$nombre} eliminadas. Puede volver a recibir la encuesta."]);
    }

    public function participantes(Request $request)
    {
        Gate::authorize('administrar', EncuestaSatisfaccion::class);
        $f = $request->validate(['evento_id' => 'required|exists:eventos,id']);

        $lista = $this->encuestas->participantes((int) $f['evento_id'])->map(fn ($p) => [
            'user_id'        => $p['user_id'],
            'nombre'         => $p['name'],
            'email'          => $p['email'],
            'tipo'           => $p['tipo'],
            'nombre_negocio' => $p['nombre_negocio'],
            'ya_enviada'     => $p['ya_enviada'],
        ])->values();

        return response()->json(['data' => $lista, 'meta' => [
            'total'      => $lista->count(),
            'pendientes' => $lista->where('ya_enviada', false)->count(),
        ]]);
    }

    public function enviarEvento(Request $request)
    {
        Gate::authorize('administrar', EncuestaSatisfaccion::class);
        $d = $request->validate([
            'evento_id'    => 'required|exists:eventos,id',
            'plantilla_id' => 'nullable|exists:encuesta_plantillas,id',
            'segmento'     => ['required', Rule::in(EncuestasAdminService::SEGMENTOS_ENVIO)],
        ]);

        $n = $this->encuestas->enviarParaEvento((int) $d['evento_id'], $this->plantillaId($d), $d['segmento']);

        return response()->json(['data' => ['enviadas' => $n], 'message' => "Encuestas enviadas a {$n} participante(s)."]);
    }

    public function enviarSeleccion(Request $request)
    {
        Gate::authorize('administrar', EncuestaSatisfaccion::class);
        $d = $request->validate([
            'evento_id'    => 'required|exists:eventos,id',
            'plantilla_id' => 'nullable|exists:encuesta_plantillas,id',
            'user_ids'     => 'required|array|min:1',
            'user_ids.*'   => 'exists:users,id',
        ]);

        $n = $this->encuestas->enviarSeleccion((int) $d['evento_id'], $this->plantillaId($d), $d['user_ids']);

        return response()->json(['data' => ['enviadas' => $n], 'message' => "Encuesta enviada a {$n} participante(s) seleccionado(s)."]);
    }

    public function enviarPrueba(Request $request)
    {
        Gate::authorize('administrar', EncuestaSatisfaccion::class);
        $d = $request->validate([
            'email'        => 'required|email',
            'plantilla_id' => 'nullable|exists:encuesta_plantillas,id',
        ]);

        $e = $this->encuestas->enviarPrueba($d['email'], $this->plantillaId($d));

        return response()->json(['data' => ['encuesta_id' => $e->id], 'message' => "Encuesta de prueba enviada a {$d['email']}."]);
    }

    public function enviarRecordatorio(Request $request)
    {
        Gate::authorize('administrar', EncuestaSatisfaccion::class);
        $d = $request->validate(['evento_id' => 'required|exists:eventos,id']);

        $r = $this->encuestas->enviarRecordatorio((int) $d['evento_id']);

        return response()->json(['data' => [
            'enviados'       => $r['enviados'],
            'ya_contestaron' => $r['ya_contestaron'],
            'personas'       => $r['personas'],
        ], 'message' => $r['mensaje']]);
    }

    public function enviarRecordatorioPrueba(Request $request)
    {
        Gate::authorize('administrar', EncuestaSatisfaccion::class);
        $d = $request->validate([
            'email'        => 'required|email',
            'plantilla_id' => 'nullable|exists:encuesta_plantillas,id',
        ]);

        $e = $this->encuestas->enviarRecordatorioPrueba($d['email'], $this->plantillaId($d));

        return response()->json(['data' => ['encuesta_id' => $e->id], 'message' => "Correo de recordatorio de prueba enviado a {$d['email']}."]);
    }

    private function plantillaId(array $d): ?int
    {
        return isset($d['plantilla_id']) ? (int) $d['plantilla_id'] : null;
    }
}
