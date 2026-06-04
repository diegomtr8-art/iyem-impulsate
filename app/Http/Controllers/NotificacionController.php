<?php

namespace App\Http\Controllers;

use App\Models\Notificacion;
use Illuminate\Http\Request;

class NotificacionController extends Controller
{
    public function index(Request $request)
    {
        $notificaciones = Notificacion::where('user_id', $request->user()->id)
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
        $notificacion->update(['leida' => true]);
        return response()->json(['ok' => true]);
    }

    public function marcarTodasLeidas(Request $request)
    {
        Notificacion::where('user_id', $request->user()->id)
            ->where('leida', false)
            ->update(['leida' => true]);

        return response()->json(['ok' => true]);
    }
}
