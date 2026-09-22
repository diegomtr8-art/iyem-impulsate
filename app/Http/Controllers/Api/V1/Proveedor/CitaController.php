<?php

namespace App\Http\Controllers\Api\V1\Proveedor;

use App\Http\Controllers\Controller;
use App\Http\Resources\CitaProveedorResource;
use App\Models\Cita;
use App\Services\AgendaCitaService;
use App\Services\GestionCitasProveedorService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Citas RECIBIDAS por el proveedor: mismas pestanas, contadores, correos y
 * notificaciones que /proveedor/citas de la web (GestionCitasProveedorService).
 * A diferencia de la web, aceptar/rechazar/reagendar comprueban el estado (422).
 */
class CitaController extends Controller
{
    public function __construct(
        private GestionCitasProveedorService $gestion,
        private AgendaCitaService $agenda,
    ) {}

    public function index(Request $request)
    {
        $datos = $request->validate([
            'tab'      => ['nullable', Rule::in(array_keys(GestionCitasProveedorService::PESTANAS))],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $tab = $datos['tab'] ?? 'pendientes';
        $restaurantero = $request->user()->restaurantero;

        if (!$restaurantero) {
            // La web responde 403; en la app es mas util una lista vacia con el motivo.
            return response()->json([
                'data' => [],
                'meta' => [
                    'tab'        => $tab,
                    'contadores' => ['pendientes' => 0, 'confirmadas' => 0, 'rechazadas' => 0],
                    'motivo'     => 'Aún no tienes perfil de negocio. Complétalo para recibir citas.',
                ],
            ]);
        }

        $citas = $this->gestion->citasDePestana($restaurantero, $tab)
            ->with(['cliente', 'evento'])
            ->orderByDesc('id')
            ->paginate((int) ($datos['per_page'] ?? 20))
            ->withQueryString();

        return CitaProveedorResource::collection($citas)->additional(['meta' => [
            'tab'        => $tab,
            'contadores' => $this->gestion->contadores($restaurantero),
        ]]);
    }

    public function show(Cita $cita)
    {
        Gate::authorize('verComoProveedor', $cita);

        return new CitaProveedorResource($cita->load(['cliente', 'evento']));
    }

    public function aceptar(Cita $cita)
    {
        Gate::authorize('responder', $cita);
        $this->gestion->asegurarAccion($cita, 'aceptar');

        $this->gestion->aceptar($cita);

        return $this->respuesta($cita, 'Cita aceptada. El comprador fue notificado.');
    }

    public function rechazar(Cita $cita)
    {
        Gate::authorize('responder', $cita);
        $this->gestion->asegurarAccion($cita, 'rechazar');

        $this->gestion->rechazar($cita);

        return $this->respuesta($cita, 'Cita rechazada. El comprador fue notificado.');
    }

    public function reagendar(Request $request, Cita $cita)
    {
        Gate::authorize('responder', $cita);
        $this->gestion->asegurarAccion($cita, 'reagendar');

        $datos = $request->validate(GestionCitasProveedorService::REGLAS_REAGENDAR);

        // Hueco ocupado para el proveedor o el comprador -> 422 en 'fecha'.
        $this->gestion->proponerReagenda($cita, $datos['propuesta_fecha'], $datos['propuesta_hora']);

        return $this->respuesta($cita, 'Propuesta de reagendamiento enviada al comprador.');
    }

    /** Horarios que el proveedor puede proponer para esta cita (mismas reglas que reagendar). */
    public function disponibilidad(Request $request, Cita $cita)
    {
        Gate::authorize('verComoProveedor', $cita);

        $datos = $request->validate([
            'fecha' => ['nullable', 'date_format:Y-m-d'],
            'desde' => ['nullable', 'date_format:Y-m-d', 'prohibits:fecha'],
            'hasta' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:desde', 'prohibits:fecha'],
        ]);

        $desde = $datos['fecha'] ?? $datos['desde'] ?? null;
        $hasta = $datos['fecha'] ?? $datos['hasta'] ?? null;

        return response()->json(['data' => array_merge(
            ['cita_id' => $cita->id, 'puede_reagendar' => $this->gestion->acciones($cita)['puede_reagendar']],
            $this->agenda->disponibilidadReagenda($cita, $desde ? Carbon::parse($desde) : null, $hasta ? Carbon::parse($hasta) : null),
        )]);
    }

    private function respuesta(Cita $cita, string $mensaje)
    {
        return (new CitaProveedorResource($cita->fresh(['cliente', 'evento'])))->additional(['message' => $mensaje]);
    }
}
