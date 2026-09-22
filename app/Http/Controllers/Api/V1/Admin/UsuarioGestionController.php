<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\AdminUsuarioResource;
use App\Models\User;
use App\Services\Admin\GestionUsuariosService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Gestion de cuentas SOLO para super-admin (web: /admin/usuarios-gestion,
 * middleware 'super-admin'). GestionUsuariosService.
 */
class UsuarioGestionController extends Controller
{
    public function __construct(private GestionUsuariosService $gestion) {}

    public function index(Request $request)
    {
        Gate::authorize('gestionarCuentas', User::class);
        $f = $request->validate([
            'search'   => ['nullable', 'string', 'max:100'],
            'rol'      => ['nullable', 'string', 'max:50'],
            'canirac'  => ['nullable', Rule::in(['si', 'no'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        return AdminUsuarioResource::collection($this->gestion->listar($f, (int) ($f['per_page'] ?? 20)))
            ->additional(['meta' => ['roles' => $this->gestion->roles()]]);
    }

    public function roles()
    {
        Gate::authorize('gestionarCuentas', User::class);

        return response()->json(['data' => $this->gestion->roles()]);
    }

    public function show(User $user)
    {
        Gate::authorize('gestionarCuentas', $user);

        return new AdminUsuarioResource($user);
    }

    public function update(Request $request, User $user)
    {
        Gate::authorize('gestionarCuentas', $user);

        $validados = $request->validate($this->gestion->reglasActualizar($user));
        $this->gestion->actualizar($user, $validados);

        return (new AdminUsuarioResource($user->fresh()))->additional(['message' => 'Datos del usuario actualizados correctamente.']);
    }

    public function password(Request $request, User $user)
    {
        Gate::authorize('gestionarCuentas', $user);

        $request->validate(GestionUsuariosService::REGLAS_PASSWORD);
        $this->gestion->cambiarPassword($user, $request->password, $request->user());

        return response()->json(['data' => null, 'message' => 'Contraseña actualizada correctamente.']);
    }

    public function actualizarRoles(Request $request, User $user)
    {
        Gate::authorize('gestionarCuentas', $user);

        $d = $request->validate(GestionUsuariosService::REGLAS_ROLES);
        $this->gestion->cambiarRoles($user, $d['roles'], $request->user());

        return (new AdminUsuarioResource($user->fresh()))->additional(['message' => 'Roles actualizados correctamente.']);
    }

    public function destroy(Request $request, User $user)
    {
        Gate::authorize('gestionarCuentas', $user);

        $email = $user->email;
        $this->gestion->eliminar($user, $request->user());

        return response()->json(['data' => null, 'message' => "Usuario {$email} eliminado correctamente."]);
    }
}
