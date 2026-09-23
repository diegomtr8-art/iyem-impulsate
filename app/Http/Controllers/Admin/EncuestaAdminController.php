<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EncuestaPlantilla;
use App\Models\EncuestaSatisfaccion;
use App\Models\Evento;
use App\Services\Admin\EncuestasAdminService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;

/** La logica vive en EncuestasAdminService (compartido con la API v1). */
class EncuestaAdminController extends Controller
{
    public function __construct(private EncuestasAdminService $encuestas) {}

    public function index(Request $request)
    {
        $eventoId = $request->get('evento_id');

        $ultimasRespondidas = $this->encuestas->ultimasRespondidas($eventoId ? (int) $eventoId : null)
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

        $encuestas = $this->encuestas->listar($eventoId ? (int) $eventoId : null)
            ->through(fn ($e) => [
                'id'            => $e->id,
                'evento'        => $e->evento?->only(['id', 'nombre']),
                'usuario'       => $e->user?->only(['id', 'name', 'email']),
                'tipo'          => $e->tipo,
                'completada'    => $e->completada(),
                'completada_at' => $e->completada_at?->format('d/m/Y H:i'),
                'created_at'    => $e->created_at->format('d/m/Y'),
            ]);

        return Inertia::render('Admin/Encuestas/Index', [
            'encuestas'          => $encuestas,
            'eventos'            => Evento::orderByDesc('created_at')->get(['id', 'nombre']),
            'filtroEvento'       => $eventoId,
            'plantillas'         => $this->encuestas->plantillasResumen(),
            'plantillaActiva'    => EncuestaPlantilla::where('activa', true)->first(['id', 'nombre']),
            'metricas'           => $this->encuestas->metricas($eventoId ? (int) $eventoId : null),
            'ultimasRespondidas' => $ultimasRespondidas,
            'recordatorioResultado' => session('recordatorio_resultado'),
        ]);
    }

    public function verRespuesta(EncuestaSatisfaccion $encuesta)
    {
        $encuesta = $this->encuestas->detalle($encuesta);

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
        $canirac     = $request->get('canirac'); // 'si' | 'no' | null

        $g = $this->encuestas->graficas([
            'evento_id'    => $eventoId,
            'plantilla_id' => $plantillaId,
            'segmento'     => $segmento,
            'desde'        => $desde,
            'hasta'        => $hasta,
            'canirac'      => $canirac,
        ]);

        return Inertia::render('Admin/Encuestas/Graficas', [
            'plantilla'  => $g['plantilla'] ? [
                'id'        => $g['plantilla']->id,
                'nombre'    => $g['plantilla']->nombre,
                'preguntas' => $g['plantilla']->preguntas,
            ] : null,
            'plantillas' => EncuestaPlantilla::orderByDesc('activa')->get(['id', 'nombre', 'activa']),
            'eventos'    => Evento::orderByDesc('created_at')->get(['id', 'nombre']),
            'filtros'    => compact('eventoId', 'plantillaId', 'segmento', 'desde', 'hasta', 'canirac'),
            'kpis'       => $g['kpis'],
            'datos'      => $g['datos'],
            'respondientes' => $g['respondientes'],
        ]);
    }

    public function exportar(Request $request)
    {
        [$export, $filename] = $this->encuestas->exportEncuestas($request->get('evento_id') ? (int) $request->get('evento_id') : null);

        return Excel::download($export, $filename);
    }

    public function reportePdf(Request $request)
    {
        [$pdf, $filename] = $this->encuestas->reportePdf(...$this->filtrosReporte($request));

        return $pdf->download($filename);
    }

    public function reporteExcel(Request $request)
    {
        [$export, $filename] = $this->encuestas->exportReporte(...$this->filtrosReporte($request));

        return Excel::download($export, $filename);
    }

    /** [plantilla_id, evento_id, canirac] de la query. */
    private function filtrosReporte(Request $request): array
    {
        return [
            $request->get('plantilla_id') ? (int) $request->get('plantilla_id') : null,
            $request->get('evento_id') ? (int) $request->get('evento_id') : null,
            $request->get('canirac'), // 'si' | 'no' | null
        ];
    }

    // ── Plantillas ────────────────────────────────────────────────────────────

    public function plantillas()
    {
        return Inertia::render('Admin/Encuestas/Plantillas', [
            'plantillas' => $this->encuestas->plantillas(),
        ]);
    }

    public function guardarPlantilla(Request $request)
    {
        $this->encuestas->guardarPlantilla($request->validate(EncuestasAdminService::REGLAS_PLANTILLA));

        return back()->with('success', 'Plantilla guardada correctamente.');
    }

    public function activarPlantilla(EncuestaPlantilla $plantilla)
    {
        $this->encuestas->activarPlantilla($plantilla);

        return back()->with('success', "Plantilla \"{$plantilla->nombre}\" activada. Se usará en los próximos envíos.");
    }

    public function eliminarPlantilla(EncuestaPlantilla $plantilla)
    {
        $this->encuestas->eliminarPlantilla($plantilla);

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

        $enviados = $this->encuestas->enviarParaEvento((int) $request->evento_id, $request->plantilla_id ? (int) $request->plantilla_id : null, $request->segmento);

        return back()->with('success', "Encuestas enviadas a {$enviados} participante(s).");
    }

    public function enviarPrueba(Request $request)
    {
        $request->validate([
            'email'        => 'required|email',
            'plantilla_id' => 'nullable|exists:encuesta_plantillas,id',
        ]);

        $this->encuestas->enviarPrueba($request->email, $request->plantilla_id ? (int) $request->plantilla_id : null);

        return back()->with('success', "Encuesta de prueba enviada a {$request->email}.");
    }

    public function enviarRecordatorio(Request $request)
    {
        $request->validate([
            'evento_id' => 'required|exists:eventos,id',
        ]);

        return back()->with('recordatorio_resultado', $this->encuestas->enviarRecordatorio((int) $request->evento_id));
    }

    public function enviarRecordatorioPrueba(Request $request)
    {
        $request->validate([
            'email'        => 'required|email',
            'plantilla_id' => 'nullable|exists:encuesta_plantillas,id',
        ]);

        $this->encuestas->enviarRecordatorioPrueba($request->email, $request->plantilla_id ? (int) $request->plantilla_id : null);

        return back()->with('success', "Correo de recordatorio de prueba enviado a {$request->email}.");
    }

    public function eliminarRespuestas(EncuestaSatisfaccion $encuesta)
    {
        $nombre = $this->encuestas->eliminarRespuestas($encuesta);

        return back()->with('success', "Respuestas de {$nombre} eliminadas. Puede volver a recibir la encuesta.");
    }

    public function participantes(Request $request)
    {
        $request->validate(['evento_id' => 'required|exists:eventos,id']);

        return response()->json($this->encuestas->participantes((int) $request->evento_id));
    }

    public function enviarSeleccion(Request $request)
    {
        $request->validate([
            'evento_id'    => 'required|exists:eventos,id',
            'plantilla_id' => 'nullable|exists:encuesta_plantillas,id',
            'user_ids'     => 'required|array|min:1',
            'user_ids.*'   => 'exists:users,id',
        ]);

        $enviados = $this->encuestas->enviarSeleccion(
            (int) $request->evento_id,
            $request->plantilla_id ? (int) $request->plantilla_id : null,
            $request->user_ids,
        );

        return back()->with('success', "Encuesta enviada a {$enviados} participante(s) seleccionado(s).");
    }
}
