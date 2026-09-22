<?php

namespace App\Services;

use App\Models\Cita;
use App\Models\Notificacion;
use App\Models\User;

/**
 * Notificaciones del usuario. Lo usan NotificacionController (polling de la
 * web en /api/notificaciones) y la API v1.
 */
class NotificacionService
{
    /**
     * Recordatorios "cita en ~30 min" que se generan al consultar las
     * notificaciones (admin: todas las citas; proveedor: las suyas).
     */
    public function generarRecordatorios(User $user): void
    {
        if ($user->hasRole('admin')) {
            $this->generarRecordatorios30m($user);
        }

        if ($user->hasRole('restaurantero') && $user->restaurantero) {
            $this->generarRecordatorios30mProveedor($user);
        }
    }

    public function marcarLeida(Notificacion $notificacion): void
    {
        $notificacion->update(['leida' => true]);
    }

    public function marcarTodasLeidas(User $user): int
    {
        return Notificacion::where('user_id', $user->id)
            ->where('leida', false)
            ->update(['leida' => true]);
    }

    public function eliminarTodas(User $user): int
    {
        return Notificacion::where('user_id', $user->id)->delete();
    }

    public function contarNoLeidas(User $user): int
    {
        return Notificacion::where('user_id', $user->id)->where('leida', false)->count();
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
                        "[Admin] Cita en ~30 min: {$hora}",
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
                        "Cita en ~30 min a las {$hora}",
                        "Con {$cita->cliente?->name} a las {$hora}.",
                        $cita->id
                    );
                }
            });
    }
}
