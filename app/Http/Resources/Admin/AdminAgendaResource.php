<?php

namespace App\Http\Resources\Admin;

use App\Http\Resources\AgendaPropuestaResource;
use App\Http\Resources\Concerns\UrlPublica;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Agenda propuesta vista por el admin. Precargar: comprador, evento,
 * citas.proveedor. Nunca el token del enlace del correo.
 */
class AdminAgendaResource extends JsonResource
{
    use UrlPublica;

    public function toArray(Request $request): array
    {
        return [
            'id'            => $this->id,
            'estado'        => $this->estado,
            'estado_texto'  => AgendaPropuestaResource::ESTADOS_TEXTO[$this->estado] ?? ucfirst((string) $this->estado),
            'enviada'       => $this->enviada_at !== null,
            'puede_enviar'  => $this->estado === 'pendiente',
            'evento'        => $this->evento ? ['id' => $this->evento->id, 'nombre' => $this->evento->nombre] : null,
            'comprador'     => $this->comprador ? [
                'id'             => $this->comprador->id,
                'nombre'         => $this->comprador->name,
                'nombre_empresa' => $this->comprador->nombre_empresa,
                'email'          => $this->comprador->email,
            ] : null,
            'citas'         => $this->citas->sortBy('slot_inicio')->values()->map(fn ($c) => [
                'id'        => $c->id,
                'inicio'    => $c->slot_inicio?->toIso8601String(),
                'fin'       => $c->slot_fin?->toIso8601String(),
                'cita_id'   => $c->cita_id,
                'proveedor' => $c->proveedor ? [
                    'id'             => $c->proveedor->id,
                    'nombre_negocio' => $c->proveedor->nombre_restaurante,
                    'logo_url'       => self::urlPublica($c->proveedor->logo_path),
                ] : null,
            ])->all(),
            'enviada_at'    => $this->enviada_at?->toIso8601String(),
            'respondida_at' => $this->respondida_at?->toIso8601String(),
            'creada_at'     => $this->created_at?->toIso8601String(),
        ];
    }
}
