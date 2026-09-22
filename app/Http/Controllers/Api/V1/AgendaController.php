<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\ReglaDeNegocioException;
use App\Http\Controllers\Controller;
use App\Http\Resources\AgendaPropuestaResource;
use App\Models\AgendaPropuesta;
use App\Services\AgendaPropuestaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Agendas propuestas por el admin al comprador. Misma logica que el enlace
 * del correo (AgendaPublicaController): AgendaPropuestaService.
 */
class AgendaController extends Controller
{
    public function __construct(private AgendaPropuestaService $agendas) {}

    public function index(Request $request)
    {
        $request->validate(['per_page' => ['nullable', 'integer', 'min:1', 'max:50']]);

        $agendas = AgendaPropuesta::query()
            ->where('user_id', $request->user()->id)
            ->whereNotNull('enviada_at')
            ->with(['evento', 'citas.proveedor'])
            ->orderByRaw("estado = 'pendiente' desc")
            ->orderByDesc('enviada_at')
            ->orderByDesc('id')
            ->paginate((int) $request->input('per_page', 20))
            ->withQueryString();

        return AgendaPropuestaResource::collection($agendas);
    }

    public function show(AgendaPropuesta $agenda)
    {
        Gate::authorize('view', $agenda);

        return new AgendaPropuestaResource($agenda->load(['evento', 'citas.proveedor']));
    }

    public function aceptar(AgendaPropuesta $agenda)
    {
        Gate::authorize('responder', $agenda);

        if (!$this->agendas->aceptar($agenda)) {
            throw new ReglaDeNegocioException('Esta propuesta de agenda ya fue respondida.');
        }

        return (new AgendaPropuestaResource($agenda->fresh(['evento', 'citas.proveedor'])))
            ->additional(['message' => '¡Agenda aceptada! Tus citas han sido registradas en el sistema.']);
    }

    public function rechazar(AgendaPropuesta $agenda)
    {
        Gate::authorize('responder', $agenda);

        if (!$this->agendas->rechazar($agenda)) {
            throw new ReglaDeNegocioException('Esta propuesta de agenda ya fue respondida.');
        }

        return (new AgendaPropuestaResource($agenda->fresh(['evento', 'citas.proveedor'])))
            ->additional(['message' => 'Has rechazado la propuesta de agenda. El equipo de IMPULSATE te contactará pronto.']);
    }
}
