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

    /** Ver cuentas desde el panel admin (/admin/usuarios, /admin/clientes). */
    public function administrar(User $user, ?User $objetivo = null): bool
    {
        return $user->hasRole('admin');
    }

    /**
     * Editar o borrar una cuenta desde /api/v1/admin/usuarios. Un admin NO
     * puede tocar cuentas de administrador ni de super-admin por aqui (eso es
     * de /admin/usuarios-gestion, solo super-admin).
     *
     * OJO: la web (/admin/clientes/{user} PUT) no tiene esta restriccion al
     * editar; se reporto como bug, no se cambio la web.
     */
    public function editarComoAdmin(User $user, User $objetivo): \Illuminate\Auth\Access\Response
    {
        if (!$user->hasRole('admin')) {
            return \Illuminate\Auth\Access\Response::deny();
        }

        return ($objetivo->hasRole('admin') || $objetivo->hasRole('super-admin'))
            ? \Illuminate\Auth\Access\Response::deny('Las cuentas de administrador solo las gestiona el super-administrador.')
            : \Illuminate\Auth\Access\Response::allow();
    }

    /** Gestion completa de cuentas (datos, contraseña, roles, baja): solo super-admin. */
    public function gestionarCuentas(User $user, ?User $objetivo = null): bool
    {
        return $user->hasRole('super-admin');
    }
}
