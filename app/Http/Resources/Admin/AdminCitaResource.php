<?php

namespace App\Http\Resources\Admin;

use App\Http\Resources\CitaResource;
use App\Http\Resources\Concerns\UrlPublica;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Cita vista por el admin. Precargar: restaurantero, cliente, servicio, evento. */
class AdminCitaResource extends JsonResource
{
    use UrlPublica;

    public function toArray(Request $request): array
    {
        return [
            'id'                => $this->id,
            'inicio'            => $this->inicio?->toIso8601String(),
            'fin'               => $this->fin?->toIso8601String(),
            'estado'            => $this->estado,
            'estado_texto'      => CitaResource::ESTADOS_TEXTO[$this->estado] ?? ucfirst((string) $this->estado),
            'estado_tv'         => $this->estado_tv ?? 'pendiente',
            'mesa'              => $this->mesa,
            'notas'             => $this->notas,
            'productos_interes' => $this->productos_interes ?: [],
            'evento'            => $this->evento ? ['id' => $this->evento->id, 'nombre' => $this->evento->nombre] : null,
            'proveedor'         => $this->restaurantero ? [
                'id'             => $this->restaurantero->id,
                'nombre_negocio' => $this->restaurantero->nombre_restaurante,
                'categoria'      => $this->restaurantero->categoria,
                'logo_url'       => self::urlPublica($this->restaurantero->logo_path),
            ] : null,
            'comprador'         => $this->cliente ? [
                'id'             => $this->cliente->id,
                'nombre'         => $this->cliente->name,
                'email'          => $this->cliente->email,
                'telefono'       => $this->cliente->telefono,
                'nombre_empresa' => $this->cliente->nombre_empresa,
            ] : null,
            'servicio'          => $this->servicio ? ['id' => $this->servicio->id, 'nombre' => $this->servicio->nombre] : null,
            'propuesta_reagenda' => $this->estado === 'pendiente_reconfirmacion' && $this->propuesta_inicio ? [
                'inicio' => $this->propuesta_inicio->toIso8601String(),
                'fin'    => $this->propuesta_fin?->toIso8601String(),
            ] : null,
            'creada_at'         => $this->created_at?->toIso8601String(),
        ];
    }
}
