<?php

namespace App\Policies;

use App\Models\Cita;
use App\Models\User;

class CitaPolicy
{
    public function view(User $user, Cita $cita): bool
    {
        return $this->esAdmin($user)
            || $this->esComprador($user, $cita)
            || $this->esProveedor($user, $cita);
    }

    public function cancelar(User $user, Cita $cita): bool
    {
        return $this->esComprador($user, $cita)
            && !in_array($cita->estado, ['completada', 'cancelada']);
    }

    /** Aceptar, rechazar o proponer otro horario: solo el proveedor de la cita. */
    public function responder(User $user, Cita $cita): bool
    {
        return $this->esProveedor($user, $cita);
    }

    public function cambiarEstado(User $user, Cita $cita): bool
    {
        return $this->esAdmin($user);
    }

    private function esAdmin(User $user): bool
    {
        return $user->hasRole('admin') || $user->hasRole('super-admin');
    }

    private function esComprador(User $user, Cita $cita): bool
    {
        return (int) $cita->cliente_id === (int) $user->id;
    }

    private function esProveedor(User $user, Cita $cita): bool
    {
        $restaurantero = $user->restaurantero;

        return $restaurantero && (int) $cita->restaurantero_id === (int) $restaurantero->id;
    }
}
