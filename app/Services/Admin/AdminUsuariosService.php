<?php

namespace App\Services\Admin;

use App\Exceptions\ReglaDeNegocioException;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Usuarios / compradores desde el panel admin (/admin/usuarios, /admin/clientes).
 * Lo usan Admin\UserAdminController (web) y la API v1.
 */
class AdminUsuariosService
{
    public const CAMPOS_EDICION = [
        'id', 'name', 'email', 'telefono', 'rfc', 'municipio',
        'nombre_empresa', 'sitio_web', 'camara_asociacion', 'nombre_establecimiento',
    ];

    /** Todos menos los administradores, con su numero de citas como comprador. */
    public function listar(?string $search, int $porPagina = 20): LengthAwarePaginator
    {
        $query = User::withCount(['citasComoCliente as citas_count'])
            ->orderByDesc('created_at')
            ->whereDoesntHave('roles', fn ($q) => $q->where('name', 'admin'));

        if (filled($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        return $query->paginate($porPagina)->withQueryString();
    }

    /** Ficha del comprador: citas (con proveedor y evento), eventos y contadores. */
    public function detalle(User $user): array
    {
        $user->load([
            'citasComoCliente' => function ($q) {
                $q->with([
                    'restaurantero:id,nombre_restaurante',
                    'evento:id,nombre',
                ])->orderByDesc('inicio');
            },
        ]);

        return [
            'cliente'          => $user,
            'eventosAsistidos' => $this->eventos($user),
            'stats'            => [
                'total'     => $user->citasComoCliente()->count(),
                'aceptadas' => $user->citasComoCliente()->where('estado', 'confirmada')->count(),
                'pendientes'=> $user->citasComoCliente()->where('estado', 'pendiente')->count(),
            ],
        ];
    }

    public function eventos(User $user): Collection
    {
        return DB::table('evento_usuario')
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
    }

    public function reglasActualizar(User $user): array
    {
        return [
            'name'                   => 'required|string|max:255',
            'email'                  => 'required|email|max:255|unique:users,email,' . $user->id,
            'telefono'               => 'nullable|string|max:30',
            'rfc'                    => 'nullable|string|max:13',
            'municipio'              => 'nullable|string|max:100',
            'nombre_empresa'         => 'nullable|string|max:255',
            'sitio_web'              => 'nullable|url|max:255',
            'camara_asociacion'      => 'nullable|string|max:255',
            'nombre_establecimiento' => 'nullable|string|max:255',
        ];
    }

    public function actualizar(User $user, array $validados): User
    {
        $user->update($validados);

        return $user;
    }

    public function eliminar(User $user): void
    {
        // No eliminar admins
        if ($user->hasRole('admin')) {
            throw new ReglaDeNegocioException('No puedes eliminar un administrador.');
        }

        $user->delete();
    }
}
