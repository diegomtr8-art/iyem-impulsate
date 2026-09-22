<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Evento visto por el ADMIN: toda la configuracion editable + contadores.
 * Lista blanca: nunca tv_token (va por GET /admin/eventos/{id}/tv) ni rutas
 * de disco de las imagenes (solo URL).
 */
class AdminEventoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $fecha = fn ($v) => $v?->toIso8601String();

        return [
            'id'                            => $this->id,
            'nombre'                        => $this->nombre,
            'descripcion'                   => $this->descripcion,
            'sector_economico'              => $this->sector_economico,
            'tipo_evento'                   => $this->tipo_evento ?? 'encuentro_negocios',
            'activa'                        => (bool) $this->activa,
            'destacado'                     => (bool) $this->destacado,
            'estado'                        => $this->estado(),
            'imagen_url'                    => $this->imagen_url,
            'imagen_carrusel_url'           => $this->imagen_carrusel_url,
            'convocatoria_url'              => $this->convocatoria_url,
            'fecha_hora_inicio'             => $fecha($this->fecha_hora_inicio),
            'fecha_hora_fin'                => $fecha($this->fecha_hora_fin),
            'fecha_hora_inicio_proveedores' => $fecha($this->fecha_hora_inicio_proveedores),
            'fecha_hora_fin_proveedores'    => $fecha($this->fecha_hora_fin_proveedores),
            'fecha_hora_inicio_compradores' => $fecha($this->fecha_hora_inicio_compradores),
            'fecha_hora_fin_compradores'    => $fecha($this->fecha_hora_fin_compradores),
            'fecha_aceptacion_solicitudes'  => $fecha($this->fecha_aceptacion_solicitudes),
            'fecha_corte'                   => $this->fecha_corte?->toDateString(),
            'max_citas_por_comprador'       => $this->max_citas_por_comprador !== null ? (int) $this->max_citas_por_comprador : null,
            'tiempo_entre_citas_minutos'    => $this->tiempo_entre_citas_minutos !== null ? (int) $this->tiempo_entre_citas_minutos : null,
            'max_espacios'                  => $this->max_espacios !== null ? (int) $this->max_espacios : null,
            'con_criterios_evaluacion'      => (bool) $this->con_criterios_evaluacion,
            'criterios'                     => $this->whenLoaded('criterios', fn () => $this->criterios->map(fn ($c) => [
                'id'         => $c->id,
                'nombre'     => $c->nombre,
                'porcentaje' => (float) $c->porcentaje,
                'orden'      => (int) $c->orden,
            ])->values()->all()),
            'contadores'                    => $this->when(isset($this->citas_count), fn () => [
                'citas'                  => (int) $this->citas_count,
                'proveedores'            => (int) $this->restauranteros_count,
                'compradores_con_cita'   => (int) ($this->usuarios_count ?? 0),
                'solicitudes_pendientes' => (int) ($this->solicitudes_pendientes ?? 0),
            ]),
            'creado_at'                     => $fecha($this->created_at),
        ];
    }

    /** activo | finalizado | inactivo (misma idea que la insignia de la lista web). */
    private function estado(): string
    {
        if ($this->fecha_hora_fin && now()->gt($this->fecha_hora_fin)) {
            return 'finalizado';
        }

        return $this->activa ? 'activo' : 'inactivo';
    }
}
