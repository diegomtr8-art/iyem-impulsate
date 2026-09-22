<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** El popup de publicidad que la web muestra (publicidadActiva en HandleInertiaRequests). */
class PublicidadResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                    => $this->id,
            'titulo'                => $this->titulo,
            'imagen_url'            => $this->imagen_url,
            'enlace'                => $this->enlace,
            'abre_en_nueva_pestana' => (bool) $this->abre_en_nueva_pestana,
            'fecha_fin'             => $this->fecha_fin?->toIso8601String(),
        ];
    }
}
