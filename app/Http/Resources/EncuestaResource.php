<?php

namespace App\Http\Resources;

use App\Services\EncuestaRespuestaService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Encuesta de satisfaccion. Las preguntas solo viajan en el detalle
 * (->conPreguntas()).
 */
class EncuestaResource extends JsonResource
{
    private bool $conPreguntas = false;

    public function conPreguntas(): static
    {
        $this->conPreguntas = true;

        return $this;
    }

    public function toArray(Request $request): array
    {
        return [
            'id'            => $this->id,
            'tipo'          => $this->tipo,
            'evento'        => $this->evento ? [
                'id'               => $this->evento->id,
                'nombre'           => $this->evento->nombre,
                'sector_economico' => $this->evento->sector_economico,
            ] : null,
            'completada'    => $this->completada_at !== null,
            'completada_at' => $this->completada_at?->toIso8601String(),
            'enviada_at'    => $this->created_at?->toIso8601String(),
            'preguntas'     => $this->when($this->conPreguntas, fn () => $this->preguntas()),
        ];
    }

    /** Forma normalizada de cada pregunta (la misma que interpreta Encuestas/Responder.vue). */
    private function preguntas(): array
    {
        return collect(app(EncuestaRespuestaService::class)->preguntas($this->resource))
            ->map(function ($p) {
                $tipo = $p['tipo'] ?? 'texto';

                return array_filter([
                    'id'         => $p['id'],
                    'tipo'       => $tipo,
                    'texto'      => $p['texto'] ?? '',
                    'requerida'  => ($p['requerida'] ?? true) !== false,
                    'opciones'   => in_array($tipo, ['opciones', 'multiple']) ? ($p['opciones'] ?? []) : null,
                    'escala_min' => $tipo === 'escala' ? (int) ($p['escala_min'] ?? 1) : ($tipo === 'nps' ? 0 : null),
                    'escala_max' => $tipo === 'escala' ? (int) ($p['escala_max'] ?? 5) : ($tipo === 'nps' ? 10 : null),
                    'valores'    => $tipo === 'binario' ? ['Sí', 'No'] : null,
                    'condicion'  => !empty($p['condicion']['pregunta_id']) ? [
                        'pregunta_id' => $p['condicion']['pregunta_id'],
                        'operador'    => $p['condicion']['operador'] ?? null,
                        'valor'       => $p['condicion']['valor'] ?? null,
                    ] : null,
                ], fn ($v) => $v !== null);
            })
            ->values()
            ->all();
    }
}
