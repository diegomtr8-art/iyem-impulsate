<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\AdminCitaResource;
use App\Http\Resources\Admin\AdminUsuarioResource;
use App\Http\Resources\DocumentosUsuarioResource;
use App\Models\User;
use App\Services\Admin\AdminUsuariosService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Usuarios / compradores desde el panel admin (web: /admin/usuarios y
 * /admin/clientes/{user}). AdminUsuariosService.
 *
 * Diferencia con la web: un admin NO puede editar ni borrar por aqui cuentas
 * de administrador o super-admin (403, UserPolicy::editarComoAdmin). La web
 * solo lo impide al borrar; editar el correo de otro admin por /admin/clientes
 * se reporto como bug.
 */
class UsuarioController extends Controller
{
    public function __construct(private AdminUsuariosService $usuarios) {}

    public function index(Request $request)
    {
        Gate::authorize('administrar', User::class);
        $f = $request->validate([
            'search'   => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $pag = $this->usuarios->listar($f['search'] ?? null, (int) ($f['per_page'] ?? 20));
        $pag->getCollection()->load('roles');

        return AdminUsuarioResource::collection($pag);
    }

    /** Ficha: datos, citas como comprador, eventos, contadores e INE/CSF firmados. */
    public function show(Request $request, User $user)
    {
        Gate::authorize('administrar', $user);

        $detalle = $this->usuarios->detalle($user);
        $user->citasComoCliente->each->setRelation('cliente', $user);

        return response()->json(['data' => [
            'usuario'    => (new AdminUsuarioResource($user))->resolve($request),
            'stats'      => $detalle['stats'],
            'eventos'    => $detalle['eventosAsistidos']->map(fn ($e) => [
                'id'                => $e->id,
                'nombre'            => $e->nombre,
                'fecha_hora_inicio' => $e->fecha_hora_inicio ? Carbon::parse($e->fecha_hora_inicio)->toIso8601String() : null,
                'fecha_hora_fin'    => $e->fecha_hora_fin ? Carbon::parse($e->fecha_hora_fin)->toIso8601String() : null,
                'activa'            => (bool) $e->activa,
                'tipo'              => $e->tipo,
                'estado_registro'   => $e->estado,
            ])->values(),
            'citas'      => AdminCitaResource::collection($user->citasComoCliente)->resolve($request),
            'documentos' => (new DocumentosUsuarioResource($user))->resolve($request),
        ]]);
    }

    public function update(Request $request, User $user)
    {
        Gate::authorize('editarComoAdmin', $user);

        $validados = $request->validate($this->usuarios->reglasActualizar($user));
        $this->usuarios->actualizar($user, $validados);

        return (new AdminUsuarioResource($user->fresh()))->additional(['message' => 'Comprador actualizado correctamente.']);
    }

    public function destroy(User $user)
    {
        Gate::authorize('editarComoAdmin', $user);

        $this->usuarios->eliminar($user);

        return response()->json(['data' => null, 'message' => 'Usuario eliminado correctamente.']);
    }

    public function documentos(User $user)
    {
        Gate::authorize('verDocumentos', $user);

        return new DocumentosUsuarioResource($user);
    }
}
