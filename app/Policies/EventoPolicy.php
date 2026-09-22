<?php

namespace App\Policies;

use App\Models\Evento;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class EventoPolicy
{
    /** Eventos activos, o cualquiera en el que el usuario tenga registro. */
    public function view(User $user, Evento $evento): bool
    {
        if ($user->hasRole('admin') || $user->hasRole('super-admin')) {
            return true;
        }

        return $evento->activa || DB::table('evento_usuario')
            ->where('evento_id', $evento->id)
            ->where('user_id', $user->id)
            ->exists();
    }

    /** Las reglas finas (ventanas, rol, documentos) las decide RegistroEventoService. */
    public function registrar(User $user, Evento $evento): bool
    {
        return !$user->hasRole('admin') && !$user->hasRole('super-admin');
    }

    /**
     * Gestion del evento desde el panel admin (API /admin/eventos...): crear,
     * editar, activar, archivar, eliminar, solicitudes, bazar y TV. Mismo
     * criterio que el middleware web 'admin' (EnsureIsAdmin): rol admin.
     */
    public function administrar(User $user, ?Evento $evento = null): bool
    {
        return $user->hasRole('admin');
    }
}
