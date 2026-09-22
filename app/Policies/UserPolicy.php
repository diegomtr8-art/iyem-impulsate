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

    private function esAdmin(User $user): bool
    {
        return $user->hasRole('admin') || $user->hasRole('super-admin');
    }
}
