<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function view(User $user, User $objetivo): bool
    {
        return $user->is($objetivo) || $this->esAdmin($user);
    }

    public function update(User $user, User $objetivo): bool
    {
        return $user->is($objetivo) || $this->esAdmin($user);
    }

    /** INE / CSF: misma regla que DocumentoController (dueño o admin). */
    public function verDocumentos(User $user, User $objetivo): bool
    {
        return app(\App\Services\DocumentosUsuarioService::class)->puedeVer($user, $objetivo);
    }

    private function esAdmin(User $user): bool
    {
        return $user->hasRole('admin') || $user->hasRole('super-admin');
    }
}
