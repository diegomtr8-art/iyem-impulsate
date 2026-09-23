<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Categoria de proveedor. proveedores_activos solo si se cargo withCount. */
class CategoriaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                  => $this->id,
            'nombre'              => $this->nombre,
            'orden'               => (int) $this->orden,
            'activo'              => (bool) $this->activo,
            'proveedores_activos' => $this->when(isset($this->restauranteros_count), fn () => (int) $this->restauranteros_count),
        ];
    }
}
