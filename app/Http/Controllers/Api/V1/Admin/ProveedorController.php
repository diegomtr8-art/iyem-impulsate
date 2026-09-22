<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\AdminCitaResource;
use App\Http\Resources\Admin\AdminProveedorResource;
use App\Http\Resources\DocumentosUsuarioResource;
use App\Http\Resources\PerfilProveedorResource;
use App\Models\Restaurantero;
use App\Services\Admin\AdminProveedoresService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Proveedores desde el panel admin (web: /admin/restauranteros). Mismas
 * reglas, correos y avisos (AdminProveedoresService). Para subir logo usar
 * POST (multipart); PUT/PATCH solo sin archivo.
 */
class ProveedorController extends Controller
{
    public function __construct(private AdminProveedoresService $proveedores) {}

    public function index(Request $request)
    {
        Gate::authorize('administrar', Restaurantero::class);

        $f = $request->validate([
            'search'   => ['nullable', 'string', 'max:100'],
            'estado'   => ['nullable', Rule::in(['pendiente', 'aprobado', 'rechazado'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $pag = $this->proveedores->listar($f['search'] ?? null, (int) ($f['per_page'] ?? 15), $f['estado'] ?? null);

        return AdminProveedorResource::collection($pag)->additional(['meta' => [
            'pendientes_aprobacion' => $this->proveedores->contarPendientesAprobacion(),
        ]]);
    }

    /** Los que esperan revision (bloque "Pendientes de aprobación" de la web). */
    public function pendientes()
    {
        Gate::authorize('administrar', Restaurantero::class);

        $lista = $this->proveedores->pendientes();

        return AdminProveedorResource::collection($lista)->additional(['meta' => [
            'total'                 => $lista->count(),
            'pendientes_aprobacion' => $this->proveedores->contarPendientesAprobacion(),
        ]]);
    }

    /** Ficha: negocio, perfil completo por secciones, contadores, eventos e INE/CSF firmados. */
    public function show(Request $request, Restaurantero $restaurantero)
    {
        Gate::authorize('administrar', $restaurantero);

        $restaurantero->load('user')->loadCount('citas');

        return response()->json(['data' => [
            'proveedor'  => (new AdminProveedorResource($restaurantero))->resolve($request),
            'perfil'     => $restaurantero->user ? (new PerfilProveedorResource($restaurantero->user))->resolve($request) : null,
            'stats'      => $this->proveedores->stats($restaurantero),
            'eventos'    => $this->proveedores->eventosParticipados($restaurantero)->map(fn ($e) => [
                'id'                => $e->id,
                'nombre'            => $e->nombre,
                'fecha_hora_inicio' => $e->fecha_hora_inicio ? Carbon::parse($e->fecha_hora_inicio)->toIso8601String() : null,
                'fecha_hora_fin'    => $e->fecha_hora_fin ? Carbon::parse($e->fecha_hora_fin)->toIso8601String() : null,
                'activa'            => (bool) $e->activa,
                'estado_registro'   => $e->estado,
            ])->values(),
            'documentos' => $restaurantero->user ? (new DocumentosUsuarioResource($restaurantero->user))->resolve($request) : null,
        ]]);
    }

    /** Citas del proveedor (pestaña de la ficha web), paginadas. */
    public function citas(Request $request, Restaurantero $restaurantero)
    {
        Gate::authorize('administrar', $restaurantero);
        $f = $request->validate(['per_page' => ['nullable', 'integer', 'min:1', 'max:100']]);

        $citas = $this->proveedores->detalle($restaurantero, (int) ($f['per_page'] ?? 20))['citas'];
        $citas->getCollection()->each->setRelation('restaurantero', $restaurantero);

        return AdminCitaResource::collection($citas);
    }

    public function update(Request $request, Restaurantero $restaurantero)
    {
        Gate::authorize('administrar', $restaurantero);

        $request->validate($this->proveedores->reglasActualizar($restaurantero));
        $this->proveedores->actualizar($restaurantero, $request);

        return $this->respuesta($restaurantero, 'Perfil actualizado correctamente.');
    }

    public function categoria(Request $request, Restaurantero $restaurantero)
    {
        Gate::authorize('administrar', $restaurantero);
        $d = $request->validate(['categoria' => 'nullable|string|max:100']);

        $this->proveedores->cambiarCategoria($restaurantero, $d['categoria'] ?? null);

        return $this->respuesta($restaurantero, 'Categoría actualizada correctamente.');
    }

    /**
     * Activar / desactivar. La web solo alterna; la app puede mandar `activo`
     * explicito (idempotente). Sin `activo`, alterna como la web.
     */
    public function activo(Request $request, Restaurantero $restaurantero)
    {
        Gate::authorize('administrar', $restaurantero);
        $d = $request->validate(['activo' => ['nullable', 'boolean']]);

        if (!array_key_exists('activo', $d) || $d['activo'] === null || $request->boolean('activo') !== (bool) $restaurantero->activo) {
            $this->proveedores->alternarActivo($restaurantero);
        }

        return $this->respuesta($restaurantero, 'Estado actualizado correctamente.');
    }

    public function aprobar(Restaurantero $restaurantero)
    {
        Gate::authorize('administrar', $restaurantero);
        $this->proveedores->aprobar($restaurantero);

        return $this->respuesta($restaurantero, 'Proveedor aprobado y notificado.');
    }

    public function rechazar(Request $request, Restaurantero $restaurantero)
    {
        Gate::authorize('administrar', $restaurantero);
        $d = $request->validate(['motivo' => ['required', 'string', 'max:500']]);

        $this->proveedores->rechazar($restaurantero, $d['motivo']);

        return $this->respuesta($restaurantero, 'Perfil rechazado y proveedor notificado.');
    }

    private function respuesta(Restaurantero $restaurantero, string $mensaje)
    {
        return (new AdminProveedorResource($restaurantero->fresh('user')->loadCount('citas')))->additional(['message' => $mensaje]);
    }
}
