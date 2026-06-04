<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UserAdminController extends Controller
{
    public function index(Request $request)
    {
        $query = User::withCount(['citasComoCliente as citas_count'])
            ->orderByDesc('created_at');

        // Excluir admins
        $query->whereDoesntHave('roles', function ($q) {
            $q->where('name', 'admin');
        });

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $usuarios = $query->paginate(20)->withQueryString();

        return Inertia::render('Admin/Usuarios/Index', [
            'usuarios' => $usuarios,
            'filters'  => $request->only(['search']),
        ]);
    }

    public function show(User $user): Response
    {
        $user->loadCount('citasComoCliente as citas_como_cliente_count');
        $user->load([
            'citasComoCliente' => function ($q) {
                $q->with(['restaurantero' => fn($r) => $r->select('id', 'nombre_restaurante')])
                  ->orderByDesc('inicio');
            },
        ]);

        return Inertia::render('Admin/Clientes/Show', [
            'cliente' => $user,
        ]);
    }

    public function destroy(User $user)
    {
        // No eliminar admins
        if ($user->hasRole('admin')) {
            return back()->withErrors(['error' => 'No puedes eliminar un administrador.']);
        }

        $user->delete();

        return back()->with('success', 'Usuario eliminado correctamente.');
    }
}
