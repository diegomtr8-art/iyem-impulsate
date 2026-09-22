<?php

namespace App\Policies;

use App\Models\Notificacion;
use App\Models\User;

/** Cada quien solo ve, marca y borra sus propias notificaciones. */
class NotificacionPolicy
{
    public function view(User $user, Notificacion $notificacion): bool
    {
        return (int) $notificacion->user_id === (int) $user->id;
    }

    public function update(User $user, Notificacion $notificacion): bool
    {
        return $this->view($user, $notificacion);
    }

    public function delete(User $user, Notificacion $notificacion): bool
    {
        return $this->view($user, $notificacion);
    }
}
