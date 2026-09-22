<?php

namespace App\Policies;

use App\Models\EncuestaSatisfaccion;
use App\Models\User;

/** Cada quien solo ve y responde sus encuestas (las de prueba del admin no cuentan). */
class EncuestaSatisfaccionPolicy
{
    public function view(User $user, EncuestaSatisfaccion $encuesta): bool
    {
        return (int) $encuesta->user_id === (int) $user->id && !$encuesta->es_prueba;
    }

    public function responder(User $user, EncuestaSatisfaccion $encuesta): bool
    {
        return $this->view($user, $encuesta);
    }
}
