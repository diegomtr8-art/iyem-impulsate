<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\SolicitudEventoResource;
use App\Http\Resources\DocumentosUsuarioResource;
use App\Http\Resources\PerfilProveedorResource;
use App\Models\Evento;
use App\Models\Restaurantero;
use App\Models\User;
use App\Services\Admin\SolicitudesEventoService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Solicitudes de registro a un evento (web: /admin/eventos/{evento}/solicitudes).
 * Mismos correos y notificaciones (SolicitudesEventoService).
 */
class SolicitudController extends Controller
{
    public function __construct(private SolicitudesEventoService $solicitudes) {}

    /**
     * Lista filtrable. La web trae todo y filtra en el navegador (pestañas
     * pendientes/aprobados/rechazados, tipo y CANIRAC); aqui los mismos
     * filtros van por query y la lista se pagina.
     */
    public function index(Request $request, Evento $evento)
    {
        Gate::authorize('administrar', $evento);

        $f = $request->validate([
            'estado'   => ['nullable', Rule::in(SolicitudesEventoService::ESTADOS)],
            'tipo'     => ['nullable', Rule::in(SolicitudesEventoService::TIPOS)],
            'canirac'  => ['nullable', 'boolean'],
            'q'        => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page'     => ['nullable', 'integer', 'min:1'],
        ]);

        $todos = $this->solicitudes->registros($evento);

        $filtrados = $todos
            ->when($f['estado'] ?? null, fn ($c, $e) => $c->where('estado', $e))
            ->when($f['tipo'] ?? null, fn ($c, $t) => $c->where('tipo', $t))
            ->when($request->boolean('canirac'), fn ($c) => $c->filter(fn ($s) => ($s['user']['camara_asociacion'] ?? null) === 'CANIRAC'))
            ->when($f['q'] ?? null, function ($c, $q) {
                $q = mb_strtolower($q);
                return $c->filter(fn ($s) => str_contains(mb_strtolower(implode(' ', array_filter([
                    $s['user']['name'] ?? null, $s['user']['email'] ?? null, $s['user']['nombre_empresa'] ?? null,
                    $s['restaurantero']['nombre_restaurante'] ?? null,
                ]))), $q));
            })
            // Mas recientes primero (la web conserva el orden de insercion).
            ->sortByDesc(fn ($s) => (string) $s['created_at'])
            ->values();

        $porPagina = (int) ($f['per_page'] ?? 30);
        $pagina    = (int) ($f['page'] ?? 1);
        $paginador = new LengthAwarePaginator(
            $filtrados->forPage($pagina, $porPagina)->values(),
            $filtrados->count(),
            $porPagina,
            $pagina,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        $contar = fn (string $estado) => $todos->where('estado', $estado)->countBy('tipo')->all();

        return SolicitudEventoResource::collection($paginador)->additional(['meta' => [
            'evento'     => $evento->only(['id', 'nombre', 'activa', 'tipo_evento']),
            'contadores' => [
                'pendiente' => $contar('pendiente'),
                'aprobado'  => $contar('aprobado'),
                'rechazado' => $contar('rechazado'),
            ],
        ]]);
    }

    /** Candidatos para el alta manual (los que aun no tienen registro de ese tipo en el evento). */
    public function candidatos(Evento $evento)
    {
        Gate::authorize('administrar', $evento);

        return response()->json(['data' => [
            'proveedores' => $this->solicitudes->proveedoresSinRegistro($evento)->map(fn ($r) => [
                'id' => $r->id, 'nombre_negocio' => $r->nombre_restaurante, 'user_id' => $r->user_id,
            ])->values(),
            'compradores' => $this->solicitudes->compradoresSinRegistro($evento)->map(fn ($u) => [
                'id' => $u->id, 'nombre' => $u->name, 'nombre_empresa' => $u->nombre_empresa,
            ])->values(),
        ]]);
    }

    /**
     * Perfil completo del solicitante (el modal "Ver perfil" de la web):
     * sus solicitudes en este evento, su perfil de comprador y, si es
     * proveedor, el de negocio; INE/CSF por URL firmada temporal.
     */
    public function show(Request $request, Evento $evento, User $user)
    {
        Gate::authorize('administrar', $evento);

        $registros = $this->solicitudes->registros($evento, $user->id);
        abort_if($registros->isEmpty(), 404, 'Este usuario no tiene solicitudes en el evento.');

        return response()->json(['data' => [
            'solicitudes'      => SolicitudEventoResource::collection($registros->values())->resolve($request),
            'perfil_comprador' => [
                'curp'        => $user->curp,
                'municipio'   => $user->municipio,
                'genero'      => $user->genero,
                'roles'       => $user->getRoleNames()->values()->all(),
            ],
            'perfil_proveedor' => $user->restaurantero ? (new PerfilProveedorResource($user))->resolve($request) : null,
            'documentos'       => (new DocumentosUsuarioResource($user))->resolve($request),
        ]]);
    }

    private function tipo(Request $request, array $extra = []): array
    {
        return $request->validate(array_merge([
            'tipo' => ['required', Rule::in(SolicitudesEventoService::TIPOS)],
        ], $extra));
    }

    public function aprobar(Request $request, Evento $evento, User $user)
    {
        Gate::authorize('administrar', $evento);
        $d = $this->tipo($request);

        $this->solicitudes->aprobar($evento, $user, $d['tipo']);

        return $this->respuesta($request, $evento, $user, $d['tipo'], 'Solicitud aprobada.');
    }

    public function rechazar(Request $request, Evento $evento, User $user)
    {
        Gate::authorize('administrar', $evento);
        $d = $this->tipo($request, ['motivo_rechazo' => 'required|string|max:500']);

        $this->solicitudes->rechazar($evento, $user, $d['tipo'], $d['motivo_rechazo']);

        return $this->respuesta($request, $evento, $user, $d['tipo'], 'Solicitud rechazada.');
    }

    public function revertir(Request $request, Evento $evento, User $user)
    {
        Gate::authorize('administrar', $evento);
        $d = $this->tipo($request);

        // La web responde "regresada a pendiente" aunque no hubiera nada que
        // revertir; la API avisa con 422.
        if ($this->solicitudes->revertirPendiente($evento, $user, $d['tipo']) === 0) {
            throw new \App\Exceptions\ReglaDeNegocioException('Solo se pueden regresar a pendiente solicitudes rechazadas.');
        }

        return $this->respuesta($request, $evento, $user, $d['tipo'], 'Solicitud regresada a pendiente.');
    }

    public function eliminar(Request $request, Evento $evento, User $user)
    {
        Gate::authorize('administrar', $evento);
        $d = $this->tipo($request, ['motivo_eliminacion' => 'required|string|max:500']);

        $this->solicitudes->eliminar($evento, $user, $d['tipo'], $d['motivo_eliminacion']);

        return response()->json(['data' => null, 'message' => 'Usuario eliminado del evento.']);
    }

    public function aprobarTodos(Request $request, Evento $evento)
    {
        Gate::authorize('administrar', $evento);
        $d = $this->tipo($request);

        $total = $this->solicitudes->aprobarTodos($evento, $d['tipo']);

        return response()->json([
            'data'    => ['aprobadas' => $total],
            'message' => $total === 0 ? 'No hay solicitudes pendientes de ese tipo.' : "{$total} solicitudes aprobadas.",
        ]);
    }

    public function agregarProveedor(Request $request, Evento $evento)
    {
        Gate::authorize('administrar', $evento);
        $d = $request->validate(['restaurantero_id' => 'required|exists:restauranteros,id']);

        $restaurantero = Restaurantero::findOrFail($d['restaurantero_id']);
        $this->solicitudes->agregarProveedor($evento, $restaurantero);

        return $this->respuesta($request, $evento, $restaurantero->user, 'proveedor',
            'Proveedor "' . $restaurantero->nombre_restaurante . '" agregado al evento correctamente.', 201);
    }

    public function agregarComprador(Request $request, Evento $evento)
    {
        Gate::authorize('administrar', $evento);
        $d = $request->validate(['user_id' => 'required|exists:users,id']);

        $user = User::findOrFail($d['user_id']);
        $this->solicitudes->agregarComprador($evento, $user);

        return $this->respuesta($request, $evento, $user, 'comprador',
            'Comprador "' . ($user->nombre_empresa ?? $user->name) . '" agregado al evento correctamente.', 201);
    }

    private function respuesta(Request $request, Evento $evento, User $user, string $tipo, string $mensaje, int $status = 200)
    {
        $registro = $this->solicitudes->registros($evento, $user->id)->firstWhere('tipo', $tipo);

        return (new SolicitudEventoResource($registro))
            ->additional(['message' => $mensaje])
            ->response()->setStatusCode($status);
    }
}
