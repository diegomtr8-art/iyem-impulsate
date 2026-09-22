<?php

namespace App\Http\Resources;

use App\Http\Resources\Concerns\UrlPublica;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Tarjeta del directorio (Restauranteros/Index.vue). Lista blanca: nunca la
 * direccion (la ficha publica la oculta) ni datos internos del perfil.
 */
class ProveedorResumenResource extends JsonResource
{
    use UrlPublica;

    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'nombre_negocio' => $this->nombre_restaurante,
            'categoria'      => $this->categoria,
            'municipio'      => $this->municipio,
            'descripcion'    => $this->descripcion,
            'logo_url'       => self::urlPublica($this->logo_path),
            'citas_count'    => (int) ($this->citas_count ?? 0),
            'coincidencias'  => $this->when(isset($this->resource->coincidencias), fn () => (int) $this->resource->coincidencias),
        ];
    }
}
