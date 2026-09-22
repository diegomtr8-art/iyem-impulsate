<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\CitaResource;
use App\Models\Cita;
use App\Services\AgendaCitaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Citas del COMPRADOR. Mismas reglas y avisos que la web (CitaPublicaController
 * y las rutas por token del correo de reagenda): todo pasa por AgendaCitaService.
 */
class CitaController extends Controller
{
    public function __construct(private AgendaCitaService $agenda) {}

    public function index(Request $request)
    {
        $datos = $request->validate([
            'estado'    => ['nullable', 'string', 'max:200'],
            'evento_id' => ['nullable', 'integer'],
            'desde'     => ['nullable', 'date_format:Y-m-d'],
            'per_page'  => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $estados = array_values(array_filter(array_map('trim', explode(',', $datos['estado'] ?? ''))));
        $invalidos = array_diff($estados, array_keys(CitaResource::ESTADOS_TEXTO));
        if ($invalidos) {
            throw ValidationException::withMessages([
                'estado' => 'Estado no válido: ' . implode(', ', $invalidos) . '. Usa: ' . implode(', ', array_keys(CitaResource::ESTADOS_TEXTO)) . '.',
            ]);
        }

        $citas = Cita::query()
            ->where('cliente_id', $request->user()->id)
            ->when($estados, fn ($q) => $q->whereIn('estado', $estados))
            ->when($datos['evento_id'] ?? null, fn ($q, $id) => $q->where('edicion_id', $id))
            ->when($datos['desde'] ?? null, fn ($q, $desde) => $q->where('inicio', '>=', $desde . ' 00:00:00'))
            ->with(['restaurantero', 'evento'])
            ->orderBy('inicio')
            ->orderBy('id')
            ->paginate((int) ($datos['per_page'] ?? 20))
            ->withQueryString();

        return CitaResource::collection($citas);
    }

    public function store(Request $request)
    {
        // Mismo orden que la web: primero la regla del admin, luego la validacion.
        $this->agenda->asegurarNoEsAdmin($request->user());

        $datos = $request->validate(AgendaCitaService::REGLAS_ALTA_COMPRADOR);

        $cita = $this->agenda->agendarComoComprador($request->user(), $datos);

        return (new CitaResource($cita->load(['restaurantero', 'evento'])))
            ->additional(['message' => 'Cita solicitada correctamente.'])
            ->response()
            ->setStatusCode(201);
    }

    public function show(Cita $cita)
    {
        Gate::authorize('view', $cita);

        return new CitaResource($cita->load(['restaurantero', 'evento', 'cliente']));
    }

    public function cancelar(Cita $cita)
    {
        Gate::authorize('gestionarComoComprador', $cita);

        $this->agenda->cancelarComoComprador($cita);

        return $this->respuesta($cita, 'Cita cancelada correctamente.');
    }

    public function notas(Request $request, Cita $cita)
    {
        Gate::authorize('gestionarComoComprador', $cita);

        $request->validate(['notas' => 'nullable|string|max:1000']);
        $this->agenda->actualizarNotas($cita, $request->input('notas'));

        return $this->respuesta($cita, 'Notas actualizadas.');
    }

    public function aceptarReagenda(Cita $cita)
    {
        Gate::authorize('gestionarComoComprador', $cita);

        $this->agenda->aceptarReagenda($cita);

        return $this->respuesta($cita, '¡Cita confirmada con la nueva fecha! Nos vemos pronto.');
    }

    public function rechazarReagenda(Cita $cita)
    {
        Gate::authorize('gestionarComoComprador', $cita);

        $this->agenda->rechazarReagenda($cita);

        return $this->respuesta($cita, 'Has rechazado la propuesta. La cita quedó cancelada.');
    }

    private function respuesta(Cita $cita, string $mensaje)
    {
        return (new CitaResource($cita->fresh(['restaurantero', 'evento'])))->additional(['message' => $mensaje]);
    }
}
