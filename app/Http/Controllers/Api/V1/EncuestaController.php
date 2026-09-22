<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\EncuestaResource;
use App\Models\EncuestaSatisfaccion;
use App\Services\EncuestaRespuestaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Encuestas de satisfaccion del usuario. Misma validacion que el enlace del
 * correo (EncuestaController): EncuestaRespuestaService.
 */
class EncuestaController extends Controller
{
    public function __construct(private EncuestaRespuestaService $respuestas) {}

    public function pendientes(Request $request)
    {
        $encuestas = EncuestaSatisfaccion::query()
            ->where('user_id', $request->user()->id)
            ->whereNull('completada_at')
            ->where(fn ($q) => $q->where('es_prueba', false)->orWhereNull('es_prueba'))
            ->with('evento')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();

        return EncuestaResource::collection($encuestas);
    }

    public function show(EncuestaSatisfaccion $encuesta)
    {
        Gate::authorize('view', $encuesta);

        return (new EncuestaResource($encuesta->load(['evento', 'plantilla'])))->conPreguntas();
    }

    public function responder(Request $request, EncuestaSatisfaccion $encuesta)
    {
        Gate::authorize('responder', $encuesta);

        // La app recibe del servidor tambien la regla de "obligatoria" que en la
        // web solo aplica el navegador (Responder.vue).
        $this->respuestas->responder($encuesta->load('plantilla'), $request->all(), exigirRequeridas: true);

        return (new EncuestaResource($encuesta->fresh('evento')))
            ->additional(['message' => '¡Gracias por responder la encuesta!']);
    }
}
