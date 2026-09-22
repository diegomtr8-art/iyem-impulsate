<?php

namespace App\Http\Controllers\Admin\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Admin\GestionUsuariosService;
use Illuminate\Http\Request;
use Inertia\Inertia;

/** La logica vive en GestionUsuariosService (compartido con la API v1). */
class UsuariosGestionController extends Controller
{
    public function __construct(private GestionUsuariosService $gestion) {}

    public function index(Request $request)
    {
        return Inertia::render('Admin/SuperAdmin/Usuarios/Index', [
            'usuarios'  => $this->gestion->listar($request->only(['search', 'rol', 'canirac'])),
            'roles'     => $this->gestion->roles(),
            'filters'   => $request->only(['search', 'rol', 'canirac']),
        ]);
    }

    public function edit(User $user)
    {
        return Inertia::render('Admin/SuperAdmin/Usuarios/Edit', [
            'usuario'          => $this->gestion->datosEdicion($user),
            'rolesDisponibles' => $this->gestion->roles(),
        ]);
    }

    public function update(Request $request, User $user)
    {
        $validated = $request->validate($this->gestion->reglasActualizar($user));

        $this->gestion->actualizar($user, $validated);

        return back()->with('success', 'Datos del usuario actualizados correctamente.');
    }

    public function updatePassword(Request $request, User $user)
    {
        $request->validate(GestionUsuariosService::REGLAS_PASSWORD);

        $this->gestion->cambiarPassword($user, $request->password, $request->user());

        return back()->with('success', 'Contraseña actualizada correctamente.');
    }

    public function updateRoles(Request $request, User $user)
    {
        $request->validate(GestionUsuariosService::REGLAS_ROLES);

        $this->gestion->cambiarRoles($user, $request->roles, $request->user());

        return back()->with('success', 'Roles actualizados correctamente.');
    }

    public function destroy(User $user)
    {
        $this->gestion->eliminar($user, auth()->user());

        return redirect()->route('admin.usuarios-gestion.index')
            ->with('success', "Usuario {$user->email} eliminado correctamente.");
    }
}
