<?php

namespace App\Http\Resources\Admin;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Expositor de un bazar (fila de BazarEvaluacionService::datos()). Lista
 * blanca: sin rutas de INE/CSF ni el enlace web de documentos (la app los
 * pide con URL firmada en GET /admin/eventos/{id}/bazar/{user}).
 */
class ExpositorBazarResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $s = $this->resource;

        return [
            'user_id'                   => (int) $s->user_id,
            'nombre'                    => $s->name,
            'email'                     => $s->email,
            'nombre_empresa'            => $s->nombre_empresa,
            'telefono'                  => $s->telefono,
            'municipio'                 => $s->municipio,
            'curp'                      => $s->curp,
            'rfc'                       => $s->rfc,
            'estado'                    => $s->estado,
            'puntaje_total'             => $s->puntaje_total !== null ? (float) $s->puntaje_total : null,
            'seleccionado'              => (bool) $s->seleccionado,
            'evaluado'                  => (bool) $s->evaluado,
            'evaluaciones'              => collect($s->evaluaciones)->map(fn ($e) => [
                'criterio_id' => $e['criterio_id'],
                'nombre'      => $e['nombre'],
                'porcentaje'  => (float) $e['porcentaje'],
                'puntaje'     => $e['puntaje'] !== null ? (float) $e['puntaje'] : null,
            ])->values()->all(),
            'notas_rechazo'             => $s->notas_rechazo,
            'motivo_rechazo'            => $s->motivo_rechazo,
            'correo_aprobacion_enviado' => (bool) $s->correo_aprobacion_enviado,
            'correo_rechazo_enviado'    => (bool) $s->correo_rechazo_enviado,
            'tiene_ine'                 => (bool) $s->tiene_ine,
            'tiene_csf'                 => (bool) $s->tiene_csf,
            'csf_fecha'                 => $s->csf_fecha ? Carbon::parse($s->csf_fecha)->toDateString() : null,
            'csf_vigente'               => (bool) $s->csf_vigente,
            'solicitado_at'             => $s->created_at ? Carbon::parse($s->created_at)->toIso8601String() : null,
        ];
    }
}
