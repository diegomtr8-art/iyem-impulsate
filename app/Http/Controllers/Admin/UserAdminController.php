<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Admin\AdminUsuariosService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** La logica vive en AdminUsuariosService (compartido con la API v1). */
class UserAdminController extends Controller
{
    public function __construct(private AdminUsuariosService $usuarios) {}

    public function index(Request $request)
    {
        return Inertia::render('Admin/Usuarios/Index', [
            'usuarios' => $this->usuarios->listar($request->search),
            'filters'  => $request->only(['search']),
        ]);
    }

    public function show(User $user): Response
    {
        return Inertia::render('Admin/Clientes/Show', $this->usuarios->detalle($user));
    }

    public function destroy(User $user)
    {
        $this->usuarios->eliminar($user);

        return back()->with('success', 'Usuario eliminado correctamente.');
    }

    public function editar(User $user)
    {
        return Inertia::render('Admin/Clientes/Editar', [
            'cliente' => $user->only(AdminUsuariosService::CAMPOS_EDICION),
        ]);
    }

    public function actualizar(Request $request, User $user)
    {
        $validated = $request->validate($this->usuarios->reglasActualizar($user));

        $this->usuarios->actualizar($user, $validated);

        return redirect()->route('admin.clientes.editar', $user)
            ->with('success', 'Comprador actualizado correctamente.');
    }
}
