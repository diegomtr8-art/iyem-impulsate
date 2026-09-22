<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * Cita en forma corta (pantalla de Inicio). El comprador solo se incluye
 * cuando quien mira es el proveedor de la cita.
 */
class CitaResumenResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $proveedor = $this->restaurantero;
        $esProveedor = $proveedor && (int) $proveedor->user_id === (int) $request->user()?->id;

        return [
            'id'        => $this->id,
            'inicio'    => $this->inicio?->toIso8601String(),
            'fin'       => $this->fin?->toIso8601String(),
            'estado'    => $this->estado,
            'mesa'      => $this->mesa,
            'evento_id' => $this->edicion_id,
            'proveedor' => $proveedor ? [
                'id'              => $proveedor->id,
                'nombre_negocio'  => $proveedor->nombre_restaurante,
                'categoria'       => $proveedor->categoria,
                'logo_url'        => $proveedor->logo_path ? Storage::disk('public')->url($proveedor->logo_path) : null,
            ] : null,
            'comprador' => $this->when($esProveedor, fn () => $this->cliente ? [
                'id'             => $this->cliente->id,
                'nombre'         => $this->cliente->name,
                'nombre_empresa' => $this->cliente->nombre_empresa,
            ] : null),
        ];
    }
}
