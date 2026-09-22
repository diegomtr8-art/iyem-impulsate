<?php

namespace App\Services;

use App\Exceptions\ReglaDeNegocioException;
use App\Models\Cita;
use App\Models\User;

/**
 * Alternar entre comprador y proveedor para usuarios con doble rol.
 * Lo usan SwitchRoleController (web) y la API v1.
 */
class CambioRolService
{
    /**
     * @return string el nuevo rol activo ('comprador' | 'proveedor')
     * @throws ReglaDeNegocioException campo 'role', con el mismo mensaje que la web
     */
    public function alternar(User $user): string
    {
        if (!$user->tieneDualRol()) {
            throw new ReglaDeNegocioException('No tienes habilitado el modo dual.', 'role');
        }

        $nuevoRol = $user->active_role === 'comprador' ? 'proveedor' : 'comprador';

        // No se cambia de perfil con una cita activa en la próxima hora.
        $ahora = now();
        $enUnaHora = now()->addHour();

        $citaComoCliente = $user->citasComoCliente()
            ->whereIn('estado', ['pendiente', 'confirmada'])
            ->where('inicio', '>=', $ahora)
            ->where('inicio', '<=', $enUnaHora)
            ->first();

        if ($citaComoCliente) {
            $minutos = (int) $ahora->diffInMinutes($citaComoCliente->inicio);
            throw new ReglaDeNegocioException(
                "Tienes una cita en {$minutos} minutos. No puedes cambiar de perfil ahora.",
                'role'
            );
        }

        $restaurantero = $user->restaurantero;
        if ($restaurantero) {
            $citaComoProveedor = Cita::where('restaurantero_id', $restaurantero->id)
                ->whereIn('estado', ['pendiente', 'confirmada'])
                ->where('inicio', '>=', $ahora)
                ->where('inicio', '<=', $enUnaHora)
                ->first();

            if ($citaComoProveedor) {
                $minutos = (int) $ahora->diffInMinutes($citaComoProveedor->inicio);
                throw new ReglaDeNegocioException(
                    "Tienes una cita como proveedor en {$minutos} minutos. No puedes cambiar de perfil ahora.",
                    'role'
                );
            }
        }

        $user->update(['active_role' => $nuevoRol]);

        return $nuevoRol;
    }
}
