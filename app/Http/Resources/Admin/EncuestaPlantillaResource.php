<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Plantilla de preguntas de encuesta. Las preguntas solo en el detalle (->conPreguntas()). */
class EncuestaPlantillaResource extends JsonResource
{
    private bool $conPreguntas = false;

    public function conPreguntas(): static
    {
        $this->conPreguntas = true;

        return $this;
    }

    public function toArray(Request $request): array
    {
        $preguntas = $this->preguntas ?? [];

        return [
            'id'              => $this->id,
            'nombre'          => $this->nombre,
            'descripcion'     => $this->descripcion,
            'segmento'        => $this->segmento,
            'activa'          => (bool) $this->activa,
            'total_preguntas' => count($preguntas),
            'preguntas'       => $this->when($this->conPreguntas, fn () => array_values($preguntas)),
            'creada_at'       => $this->created_at?->toIso8601String(),
            'actualizada_at'  => $this->updated_at?->toIso8601String(),
        ];
    }
}
