<?php

namespace App\Http\Controllers;

use App\Models\Notificacion;
use App\Services\NotificacionService;
use Illuminate\Http\Request;

class NotificacionController extends Controller
{
    public function __construct(private NotificacionService $notificaciones) {}

    public function index(Request $request)
    {
        $user = $request->user();

        // Admin / proveedor: auto-generar recordatorios de citas en los próximos 30 min
        $this->notificaciones->generarRecordatorios($user);

        $notificaciones = Notificacion::where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->limit(30)
            ->get(['id', 'tipo', 'titulo', 'mensaje', 'leida', 'cita_id', 'created_at']);

        $noLeidas = $notificaciones->where('leida', false)->count();

        return response()->json([
            'notificaciones' => $notificaciones,
            'no_leidas'      => $noLeidas,
        ]);
    }

    public function marcarLeida(Request $request, Notificacion $notificacion)
    {
        if ($notificacion->user_id !== $request->user()->id) {
            abort(403);
        }
        $this->notificaciones->marcarLeida($notificacion);
        return response()->json(['ok' => true]);
    }

    public function marcarTodasLeidas(Request $request)
    {
        $this->notificaciones->marcarTodasLeidas($request->user());

        return response()->json(['ok' => true]);
    }

    public function eliminar(Request $request, Notificacion $notificacion)
    {
        if ($notificacion->user_id !== $request->user()->id) abort(403);
        $notificacion->delete();
        return response()->json(['ok' => true]);
    }

    public function eliminarTodas(Request $request)
    {
        $this->notificaciones->eliminarTodas($request->user());
        return response()->json(['ok' => true]);
    }
}
