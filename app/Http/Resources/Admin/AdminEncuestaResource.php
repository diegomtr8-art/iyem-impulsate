<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Encuesta de satisfaccion vista por el admin. Precargar: evento, user
 * (y respuestas / plantilla para el detalle: solo viajan si estan cargadas).
 * Nunca expone el token de respuesta.
 */
class AdminEncuestaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'            => $this->id,
            'tipo'          => $this->tipo,
            'segmento'      => $this->segmento,
            'es_prueba'     => (bool) $this->es_prueba,
            'evento'        => $this->evento ? ['id' => $this->evento->id, 'nombre' => $this->evento->nombre] : null,
            'usuario'       => $this->user
                ? ['id' => $this->user->id, 'nombre' => $this->user->name, 'email' => $this->user->email]
                : ($this->email_prueba ? ['id' => null, 'nombre' => null, 'email' => $this->email_prueba] : null),
            'completada'    => $this->completada_at !== null,
            'completada_at' => $this->completada_at?->toIso8601String(),
            'enviada_at'    => $this->created_at?->toIso8601String(),
            'plantilla'     => $this->whenLoaded('plantilla', fn () => $this->plantilla ? ['id' => $this->plantilla->id, 'nombre' => $this->plantilla->nombre] : null),
            'respuestas'    => $this->whenLoaded('respuestas', fn () => $this->respuestas->map(fn ($r) => [
                'id'        => $r->id,
                'pregunta'  => $r->pregunta,
                'tipo'      => $r->tipo,
                'respuesta' => $r->respuesta,
            ])->values()),
        ];
    }
}
