<?php

namespace App\Policies;

use App\Models\Cita;
use App\Models\User;
use App\Services\AgendaCitaService;

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
            && !in_array($cita->estado, AgendaCitaService::ESTADOS_NO_CANCELABLES);
    }

    /**
     * Cancelar, editar notas o responder a una reagenda como COMPRADOR: solo el
     * dueno de la cita. Las reglas de estado las decide AgendaCitaService (422).
     */
    public function gestionarComoComprador(User $user, Cita $cita): bool
    {
        return $this->esComprador($user, $cita);
    }

    /** Aceptar, rechazar o proponer otro horario: solo el proveedor de la cita. */
    public function responder(User $user, Cita $cita): bool
    {
        return $this->esProveedor($user, $cita);
    }

    /**
     * Ver la cita en las pantallas de PROVEEDOR (/api/v1/proveedor/citas/{id}),
     * con los datos de contacto del comprador: solo el proveedor de la cita
     * (ni el comprador ni el admin por esta ruta).
     */
    public function verComoProveedor(User $user, Cita $cita): bool
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

    /** Ver, crear y cambiar de estado citas desde el panel admin (web: middleware 'admin'). */
    public function administrar(User $user, ?Cita $cita = null): bool
    {
        return $user->hasRole('admin');
    }
}
