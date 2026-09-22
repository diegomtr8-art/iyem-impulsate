<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\AdminCitaResource;
use App\Models\Cita;
use App\Services\Admin\AdminCitasService;
use App\Services\Admin\AdminEventosService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Citas desde el panel admin (web: /admin/citas). AdminCitasService.
 * El evento de las citas nuevas es `evento_id` (la web usa el de la sesion);
 * sin evento_id, el evento activo principal.
 */
class CitaController extends Controller
{
    public function __construct(
        private AdminCitasService $citas,
        private AdminEventosService $eventos,
    ) {}

    public function index(Request $request)
    {
        Gate::authorize('administrar', Cita::class);
        $f = $request->validate([
            'estado'           => ['nullable', Rule::in(array_keys(\App\Http\Resources\CitaResource::ESTADOS_TEXTO))],
            'search'           => ['nullable', 'string', 'max:100'],
            'evento_id'        => ['nullable', 'integer'],
            'restaurantero_id' => ['nullable', 'integer'],
            'fecha'            => ['nullable', 'date_format:Y-m-d'],
            'per_page'         => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $pag = $this->citas->listar($f, (int) ($f['per_page'] ?? 20), $f);
        $pag->getCollection()->load('evento');

        return AdminCitaResource::collection($pag);
    }

    /** Listas para el formulario "Nueva cita" (compradores y proveedores activos). */
    public function formulario()
    {
        Gate::authorize('administrar', Cita::class);

        return response()->json(['data' => [
            'clientes'    => $this->citas->clientes()->map(fn ($u) => ['id' => $u->id, 'nombre' => $u->name, 'email' => $u->email])->values(),
            'proveedores' => $this->citas->proveedores()->map(fn ($r) => ['id' => $r->id, 'nombre_negocio' => $r->nombre_restaurante])->values(),
        ]]);
    }

    public function show(Cita $cita)
    {
        Gate::authorize('administrar', $cita);

        return new AdminCitaResource($cita->load(['restaurantero', 'cliente', 'servicio', 'evento']));
    }

    public function store(Request $request)
    {
        Gate::authorize('administrar', Cita::class);
        $datos = $request->validate(array_merge(AdminCitasService::REGLAS_ALTA, [
            'evento_id' => ['nullable', 'integer'],
        ]));

        $cita = $this->citas->crear($this->eventos->contexto($datos['evento_id'] ?? null), $datos);

        return (new AdminCitaResource($cita->load(['restaurantero', 'cliente', 'servicio', 'evento'])))
            ->additional(['message' => 'Cita agendada correctamente para ' . $cita->cliente->name . '.'])
            ->response()->setStatusCode(201);
    }

    public function estado(Request $request, Cita $cita)
    {
        Gate::authorize('administrar', $cita);
        $d = $request->validate(['estado' => ['required', Rule::in(AdminCitasService::ESTADOS_ADMIN)]]);

        $this->citas->cambiarEstado($cita, $d['estado']);

        return (new AdminCitaResource($cita->fresh(['restaurantero', 'cliente', 'servicio', 'evento'])))
            ->additional(['message' => 'Estado de la cita actualizado.']);
    }
}
