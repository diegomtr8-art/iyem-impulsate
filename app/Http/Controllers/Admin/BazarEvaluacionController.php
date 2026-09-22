<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Evento;
use App\Services\Admin\BazarEvaluacionService;
use Illuminate\Http\Request;
use Inertia\Inertia;

/** La logica vive en BazarEvaluacionService (compartido con la API v1). */
class BazarEvaluacionController extends Controller
{
    public function __construct(private BazarEvaluacionService $bazar) {}

    public function index(Evento $evento)
    {
        $this->bazar->asegurarBazar($evento);

        return Inertia::render('Admin/Eventos/BazarEvaluacion', $this->bazar->datos($evento));
    }

    /** Pre-aprobar una solicitud pendiente (pasa a "en evaluación") */
    public function aprobar(Request $request, Evento $evento, int $userId)
    {
        $this->bazar->asegurarBazar($evento);
        $this->bazar->aprobar($evento, $userId);

        return back()->with('success', 'Solicitud aprobada para evaluación.');
    }

    /** Rechazar una solicitud pendiente (motivo requerido) */
    public function rechazar(Request $request, Evento $evento, int $userId)
    {
        $this->bazar->asegurarBazar($evento);

        $request->validate([
            'motivo_rechazo' => 'required|string|max:500',
        ]);

        $this->bazar->rechazar($evento, $userId, $request->motivo_rechazo);

        return back()->with('success', 'Solicitud rechazada.');
    }

    /** Evaluar por criterios (solo para estado='aprobado') */
    public function evaluar(Request $request, Evento $evento, int $userId)
    {
        $this->bazar->asegurarBazar($evento);

        $request->validate($this->bazar->reglasEvaluar($evento));

        $this->bazar->evaluar($evento, $userId, $request->evaluaciones);

        return back()->with('success', 'Evaluación guardada.');
    }

    /** Seleccionar / deseleccionar un participante aprobado */
    public function toggleSeleccion(Request $request, Evento $evento)
    {
        $this->bazar->asegurarBazar($evento);

        $request->validate([
            'user_id'      => 'required|integer|exists:users,id',
            'seleccionado' => 'required|boolean',
        ]);

        $this->bazar->cambiarSeleccion($evento, (int) $request->user_id, $request->boolean('seleccionado'));

        return back()->with('success', $request->boolean('seleccionado') ? 'Participante seleccionado.' : 'Participante deseleccionado.');
    }

    /** Guardar notas de rechazo (por participante no seleccionado) */
    public function guardarNotasRechazo(Request $request, Evento $evento, int $userId)
    {
        $this->bazar->asegurarBazar($evento);

        $request->validate([
            'notas_rechazo' => 'required|string|max:2000',
        ]);

        $this->bazar->guardarNotasRechazo($evento, $userId, $request->notas_rechazo);

        return back()->with('success', 'Notas guardadas.');
    }

    /** Enviar correos a seleccionados */
    public function enviarAprobacion(Evento $evento)
    {
        $this->bazar->asegurarBazar($evento);
        $enviados = $this->bazar->enviarAprobacion($evento);

        return back()->with('success', "Correo de aprobación enviado a {$enviados} participante(s).");
    }

    /** Enviar correos a NO seleccionados (con token de evaluación) */
    public function enviarRechazo(Evento $evento)
    {
        $this->bazar->asegurarBazar($evento);
        $enviados = $this->bazar->enviarRechazo($evento);

        return back()->with('success', "Correo de resultado enviado a {$enviados} participante(s).");
    }
}
