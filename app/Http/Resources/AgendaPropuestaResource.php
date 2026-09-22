<?php

namespace App\Http\Resources;

use App\Http\Resources\Concerns\UrlPublica;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Agenda propuesta por el admin al comprador (lo que muestra Agenda/Responder.vue).
 * Precargar: evento, citas.proveedor.
 */
class AgendaPropuestaResource extends JsonResource
{
    use UrlPublica;

    public const ESTADOS_TEXTO = [
        'pendiente' => 'Pendiente de respuesta',
        'aceptada'  => 'Aceptada',
        'rechazada' => 'Rechazada',
    ];

    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'estado'          => $this->estado,
            'estado_texto'    => self::ESTADOS_TEXTO[$this->estado] ?? ucfirst((string) $this->estado),
            'puede_responder' => $this->estado === 'pendiente',
            'evento'          => $this->evento ? [
                'id'                => $this->evento->id,
                'nombre'            => $this->evento->nombre,
                'fecha_hora_inicio' => $this->evento->fecha_hora_inicio?->toIso8601String(),
            ] : null,
            'enviada_at'      => $this->enviada_at?->toIso8601String(),
            'respondida_at'   => $this->respondida_at?->toIso8601String(),
            'citas'           => $this->citas->sortBy('slot_inicio')->values()->map(fn ($c) => [
                'id'        => $c->id,
                'inicio'    => $c->slot_inicio?->toIso8601String(),
                'fin'       => $c->slot_fin?->toIso8601String(),
                'cita_id'   => $c->cita_id,
                'proveedor' => $c->proveedor ? [
                    'id'             => $c->proveedor->id,
                    'nombre_negocio' => $c->proveedor->nombre_restaurante,
                    'categoria'      => $c->proveedor->categoria,
                    'descripcion'    => $c->proveedor->descripcion,
                    'logo_url'       => self::urlPublica($c->proveedor->logo_path),
                    'municipio'      => $c->proveedor->municipio,
                ] : null,
            ])->all(),
        ];
    }
}
