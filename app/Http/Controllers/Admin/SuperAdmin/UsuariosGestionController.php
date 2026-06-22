<?php

namespace App\Http\Controllers\Admin\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Spatie\Permission\Models\Role;

class UsuariosGestionController extends Controller
{
    public function index(Request $request)
    {
        $query = User::with('roles')
            ->when($request->search, fn ($q, $s) =>
                $q->where(fn ($q2) =>
                    $q2->where('name', 'like', "%$s%")
                       ->orWhere('email', 'like', "%$s%")
                       ->orWhere('nombre_empresa', 'like', "%$s%")
                )
            )
            ->when($request->rol, fn ($q, $r) =>
                $q->whereHas('roles', fn ($q2) => $q2->where('name', $r))
            )
            ->when($request->canirac === 'si', fn ($q) =>
                $q->where('camara_asociacion', 'CANIRAC')
            )
            ->when($request->canirac === 'no', fn ($q) =>
                $q->where(fn ($q2) =>
                    $q2->whereNull('camara_asociacion')
                       ->orWhere('camara_asociacion', '!=', 'CANIRAC')
                )
            )
            ->orderBy('created_at', 'desc');

        $paginados = $query->paginate(20)->withQueryString();
        $paginados->getCollection()->transform(function ($user) {
            $user->roles_nombres = $user->roles->pluck('name')->toArray();
            return $user;
        });

        return Inertia::render('Admin/SuperAdmin/Usuarios/Index', [
            'usuarios'  => $paginados,
            'roles'     => Role::orderBy('name')->pluck('name'),
            'filters'   => $request->only(['search', 'rol', 'canirac']),
        ]);
    }

    public function edit(User $user)
    {
        return Inertia::render('Admin/SuperAdmin/Usuarios/Edit', [
            'usuario'     => array_merge(
                $user->only([
                    'id', 'name', 'email', 'telefono', 'curp', 'rfc',
                    'municipio', 'nombre_empresa', 'sitio_web',
                    'active_role', 'email_verified_at', 'created_at',
                    'camara_asociacion', 'nombre_establecimiento',
                ]),
                ['roles' => $user->getRoleNames()]
            ),
            'rolesDisponibles' => Role::orderBy('name')->pluck('name'),
        ]);
    }

    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
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
        ]);

        $validated['nombre_establecimiento'] = ($validated['camara_asociacion'] ?? '') === 'CANIRAC'
            ? ($validated['nombre_establecimiento'] ?? null)
            : null;

        $user->update($validated);

        return back()->with('success', 'Datos del usuario actualizados correctamente.');
    }

    public function updatePassword(Request $request, User $user)
    {
        $request->validate([
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user->update(['password' => Hash::make($request->password)]);

        Log::info('Contraseña actualizada por super-admin', [
            'admin_id'    => auth()->id(),
            'admin_email' => auth()->user()->email,
            'target_id'   => $user->id,
            'target_email'=> $user->email,
        ]);

        return back()->with('success', 'Contraseña actualizada correctamente.');
    }

    public function updateRoles(Request $request, User $user)
    {
        $request->validate([
            'roles' => ['required', 'array'],
            'roles.*' => ['string', 'exists:roles,name'],
        ]);

        // El super-admin no puede quitarse el rol super-admin a sí mismo
        if ($user->id === auth()->id() && !in_array('super-admin', $request->roles)) {
            return back()->withErrors(['roles' => 'No puedes quitarte el rol super-admin a ti mismo.']);
        }

        $user->syncRoles($request->roles);

        return back()->with('success', 'Roles actualizados correctamente.');
    }

    public function destroy(User $user)
    {
        // El super-admin no puede eliminarse a sí mismo
        if ($user->id === auth()->id()) {
            return back()->withErrors(['general' => 'No puedes eliminar tu propia cuenta.']);
        }

        $user->delete();

        return redirect()->route('admin.usuarios-gestion.index')
            ->with('success', "Usuario {$user->email} eliminado correctamente.");
    }
}
