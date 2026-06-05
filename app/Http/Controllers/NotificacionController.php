<?php

namespace App\Http\Controllers;

use App\Models\Cita;
use App\Models\Notificacion;
use App\Models\User;
use Illuminate\Http\Request;

class NotificacionController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        // Admin: auto-generar recordatorios de citas en los próximos 30 min
        if ($user->hasRole('admin')) {
            $this->generarRecordatorios30m($user);
        }

        // Proveedor: auto-generar recordatorios de sus citas próximas en 30 min
        if ($user->hasRole('restaurantero') && $user->restaurantero) {
            $this->generarRecordatorios30mProveedor($user);
        }

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

    private function generarRecordatorios30m(User $admin): void
    {
        $desde = now();
        $hasta = now()->addMinutes(35);

        Cita::whereBetween('inicio', [$desde, $hasta])
            ->whereIn('estado', ['confirmada', 'reagendada'])
            ->with(['cliente', 'restaurantero'])
            ->get()
            ->each(function (Cita $cita) use ($admin) {
                $yaExiste = Notificacion::where('user_id', $admin->id)
                    ->where('tipo', 'recordatorio_30m')
                    ->where('cita_id', $cita->id)
                    ->exists();

                if (!$yaExiste) {
                    $hora   = $cita->inicio->format('H:i');
                    $comprador  = $cita->cliente?->name ?? '—';
                    $proveedor  = $cita->restaurantero?->nombre_restaurante ?? '—';
                    Notificacion::crear(
                        $admin->id,
                        'recordatorio_30m',
                        "⏰ Cita en ~30 min: {$hora}",
                        "{$comprador} con {$proveedor} a las {$hora}.",
                        $cita->id
                    );
                }
            });
    }

    private function generarRecordatorios30mProveedor(User $user): void
    {
        $restaurantero = $user->restaurantero;
        $desde = now();
        $hasta = now()->addMinutes(35);

        Cita::where('restaurantero_id', $restaurantero->id)
            ->whereBetween('inicio', [$desde, $hasta])
            ->whereIn('estado', ['confirmada', 'reagendada'])
            ->with('cliente')
            ->get()
            ->each(function (Cita $cita) use ($user) {
                $yaExiste = Notificacion::where('user_id', $user->id)
                    ->where('tipo', 'recordatorio_30m')
                    ->where('cita_id', $cita->id)
                    ->exists();

                if (!$yaExiste) {
                    $hora = $cita->inicio->format('H:i');
                    Notificacion::crear(
                        $user->id,
                        'recordatorio_30m',
                        "⏰ Cita en ~30 min a las {$hora}",
                        "Con {$cita->cliente?->name} a las {$hora}.",
                        $cita->id
                    );
                }
            });
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
