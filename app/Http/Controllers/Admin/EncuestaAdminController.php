<?php

namespace App\Http\Controllers\Admin;

use App\Exports\EncuestasExport;
use App\Http\Controllers\Controller;
use App\Mail\EncuestaSatisfaccionMail;
use App\Models\EncuestaPlantilla;
use App\Models\EncuestaRespuesta;
use App\Models\EncuestaSatisfaccion;
use App\Models\Evento;
use App\Services\EncuestaEnvioService;
use Illuminate\Http\Request;
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

    public function exportar(Request $request)
    {
        $eventoId = $request->get('evento_id');
        $suffix   = $eventoId ? "_evento_{$eventoId}" : '_todos';
        $filename = "encuestas{$suffix}_" . now()->format('Y-m-d') . '.xlsx';

        return Excel::download(new EncuestasExport($eventoId), $filename);
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
            'id'                => 'nullable|exists:encuesta_plantillas,id',
            'nombre'            => 'required|string|max:150',
            'descripcion'       => 'nullable|string|max:500',
            'preguntas'         => 'required|array|min:1',
            'preguntas.*.id'    => 'required|string|max:80',
            'preguntas.*.tipo'  => 'required|in:escala,texto,binario',
            'preguntas.*.texto' => 'required|string|max:500',
        ]);

        EncuestaPlantilla::updateOrCreate(
            ['id' => $data['id'] ?? null],
            [
                'nombre'      => $data['nombre'],
                'descripcion' => $data['descripcion'] ?? null,
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
        $request->validate(['evento_id' => 'required|exists:eventos,id']);
        $evento   = Evento::findOrFail($request->evento_id);
        $enviados = app(EncuestaEnvioService::class)->enviarParaEvento($evento);

        return back()->with('success', "Encuestas enviadas a {$enviados} participante(s).");
    }

    public function enviarPrueba(Request $request)
    {
        $request->validate(['email' => 'required|email']);

        $plantillaActivaId = EncuestaPlantilla::where('activa', true)->value('id');

        $encuesta = EncuestaSatisfaccion::create([
            'evento_id'             => null,
            'user_id'               => null,
            'tipo'                  => 'comprador',
            'token'                 => Str::random(40),
            'encuesta_plantilla_id' => $plantillaActivaId,
            'es_prueba'             => true,
            'email_prueba'          => $request->email,
        ]);

        Mail::to($request->email)->send(new EncuestaSatisfaccionMail($encuesta->load('plantilla'), esPrueba: true));

        return back()->with('success', "Encuesta de prueba enviada a {$request->email}.");
    }
}
