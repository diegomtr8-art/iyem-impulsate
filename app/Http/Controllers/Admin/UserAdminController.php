<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
        $user->load([
            'citasComoCliente' => function ($q) {
                $q->with([
                    'restaurantero:id,nombre_restaurante',
                    'evento:id,nombre',
                ])->orderByDesc('inicio');
            },
        ]);

        $eventosAsistidos = DB::table('evento_usuario')
            ->join('eventos', 'evento_usuario.evento_id', '=', 'eventos.id')
            ->where('evento_usuario.user_id', $user->id)
            ->select(
                'eventos.id',
                'eventos.nombre',
                'eventos.fecha_hora_inicio',
                'eventos.fecha_hora_fin',
                'eventos.activa',
                'evento_usuario.tipo',
                'evento_usuario.estado',
            )
            ->orderByDesc('eventos.fecha_hora_inicio')
            ->get();

        $totalCitas = $user->citasComoCliente()->count();
        $citasAcept = $user->citasComoCliente()->where('estado', 'confirmada')->count();
        $citasPend  = $user->citasComoCliente()->where('estado', 'pendiente')->count();

        return Inertia::render('Admin/Clientes/Show', [
            'cliente'          => $user,
            'eventosAsistidos' => $eventosAsistidos,
            'stats'            => [
                'total'     => $totalCitas,
                'aceptadas' => $citasAcept,
                'pendientes'=> $citasPend,
            ],
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
