<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Plantilla de correo (super-admin). El contenido HTML solo en el detalle (->conContenido()). */
class PlantillaCorreoResource extends JsonResource
{
    private bool $conContenido = false;

    public function conContenido(): static
    {
        $this->conContenido = true;

        return $this;
    }

    public function toArray(Request $request): array
    {
        return [
            'id'                => $this->id,
            'clave'             => $this->clave,
            'nombre'            => $this->nombre,
            'asunto'            => $this->asunto,
            'tipo_destinatario' => $this->tipo_destinatario,
            'es_sistema'        => (bool) $this->es_sistema,
            'activo'            => (bool) $this->activo,
            'contenido'         => $this->when($this->conContenido, fn () => $this->contenido),
            'actualizada_at'    => $this->updated_at?->toIso8601String(),
        ];
    }
}
