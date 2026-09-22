<?php

namespace App\Policies;

use App\Models\AgendaPropuesta;
use App\Models\User;

/**
 * Agenda propuesta por el admin: solo la ve y la responde el COMPRADOR al que
 * va dirigida, y solo cuando ya se le envio (antes es un borrador del admin).
 */
class AgendaPropuestaPolicy
{
    public function view(User $user, AgendaPropuesta $agenda): bool
    {
        return (int) $agenda->user_id === (int) $user->id && $agenda->enviada_at !== null;
    }

    public function responder(User $user, AgendaPropuesta $agenda): bool
    {
        return $this->view($user, $agenda);
    }
}
