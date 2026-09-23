<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Anuncio (popup de la portada) visto por el super-admin. Sin la ruta en disco. */
class AdminPublicidadResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                    => $this->id,
            'titulo'                => $this->titulo,
            'imagen_url'            => $this->imagen_url,
            'enlace'                => $this->enlace,
            'abre_en_nueva_pestana' => (bool) $this->abre_en_nueva_pestana,
            'fecha_inicio'          => $this->fecha_inicio?->toIso8601String(),
            'fecha_fin'             => $this->fecha_fin?->toIso8601String(),
            'activa'                => (bool) $this->activa,
            // Inactiva | Programada | Vigente | Expirada (el mismo texto de la web)
            'estado'                => $this->estado_label,
            'creada_at'             => $this->created_at?->toIso8601String(),
        ];
    }
}
