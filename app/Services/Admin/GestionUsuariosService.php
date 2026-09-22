<?php

namespace App\Services\Admin;

use App\Exceptions\ReglaDeNegocioException;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

/**
 * Gestion de cuentas EXCLUSIVA del super-admin (/admin/usuarios-gestion):
 * datos, contraseña, roles y baja de cualquier cuenta.
 * Lo usan SuperAdmin\UsuariosGestionController (web) y la API v1.
 */
class GestionUsuariosService
{
    public const CAMPOS_EDICION = [
        'id', 'name', 'email', 'telefono', 'curp', 'rfc',
        'municipio', 'nombre_empresa', 'sitio_web',
        'active_role', 'email_verified_at', 'created_at',
        'camara_asociacion', 'nombre_establecimiento',
    ];

    /** @param array{search?: ?string, rol?: ?string, canirac?: ?string} $filtros */
    public function listar(array $filtros, int $porPagina = 20): LengthAwarePaginator
    {
        $canirac = $filtros['canirac'] ?? null;

        $paginados = User::with('roles')
            ->when($filtros['search'] ?? null, fn ($q, $s) =>
                $q->where(fn ($q2) =>
                    $q2->where('name', 'like', "%$s%")
                       ->orWhere('email', 'like', "%$s%")
                       ->orWhere('nombre_empresa', 'like', "%$s%")
                )
            )
            ->when($filtros['rol'] ?? null, fn ($q, $r) =>
                $q->whereHas('roles', fn ($q2) => $q2->where('name', $r))
            )
            ->when($canirac === 'si', fn ($q) =>
                $q->where('camara_asociacion', 'CANIRAC')
            )
            ->when($canirac === 'no', fn ($q) =>
                $q->where(fn ($q2) =>
                    $q2->whereNull('camara_asociacion')
                       ->orWhere('camara_asociacion', '!=', 'CANIRAC')
                )
            )
            ->orderBy('created_at', 'desc')
            ->paginate($porPagina)
            ->withQueryString();

        $paginados->getCollection()->transform(function ($user) {
            $user->roles_nombres = $user->roles->pluck('name')->toArray();
            return $user;
        });

        return $paginados;
    }

    public function roles(): Collection
    {
        return Role::orderBy('name')->pluck('name');
    }

    public function datosEdicion(User $user): array
    {
        return array_merge(
            $user->only(self::CAMPOS_EDICION),
            ['roles' => $user->getRoleNames()]
        );
    }

    public function reglasActualizar(User $user): array
    {
        return [
            'name'                  => ['required', 'string', 'max:255'],
            'email'                 => ['required', 'email', Rule::unique('users')->ignore($user->id)],
            'telefono'              => ['nullable', 'string', 'max:20'],
            'curp'                  => ['nullable', 'string', 'max:20'],
            'rfc'                   => ['nullable', 'string', 'max:20'],
            'municipio'             => ['nullable', 'string', 'max:100'],
            'nombre_empresa'        => ['nullable', 'string', 'max:255'],
            'sitio_web'             => ['nullable', 'url', 'max:255'],
            'camara_asociacion'     => ['nullable', 'string', 'in:CANIRAC,Ninguna'],
            'nombre_establecimiento'=> ['nullable', 'string', 'max:255'],
        ];
    }

    public function actualizar(User $user, array $validados): User
    {
        $validados['nombre_establecimiento'] = ($validados['camara_asociacion'] ?? '') === 'CANIRAC'
            ? ($validados['nombre_establecimiento'] ?? null)
            : null;

        $user->update($validados);

        return $user;
    }

    public const REGLAS_PASSWORD = [
        'password' => ['required', 'string', 'min:8', 'confirmed'],
    ];

    public function cambiarPassword(User $user, string $password, User $actor): void
    {
        $user->update(['password' => Hash::make($password)]);

        Log::info('Contraseña actualizada por super-admin', [
            'admin_id'    => $actor->id,
            'admin_email' => $actor->email,
            'target_id'   => $user->id,
            'target_email'=> $user->email,
        ]);
    }

    public const REGLAS_ROLES = [
        'roles'   => ['required', 'array'],
        'roles.*' => ['string', 'exists:roles,name'],
    ];

    public function cambiarRoles(User $user, array $roles, User $actor): User
    {
        // El super-admin no puede quitarse el rol super-admin a sí mismo
        if ($user->id === $actor->id && !in_array('super-admin', $roles)) {
            throw new ReglaDeNegocioException('No puedes quitarte el rol super-admin a ti mismo.', 'roles');
        }

        $user->syncRoles($roles);

        return $user;
    }

    public function eliminar(User $user, User $actor): void
    {
        // El super-admin no puede eliminarse a sí mismo
        if ($user->id === $actor->id) {
            throw new ReglaDeNegocioException('No puedes eliminar tu propia cuenta.', 'general');
        }

        $user->delete();
    }
}
