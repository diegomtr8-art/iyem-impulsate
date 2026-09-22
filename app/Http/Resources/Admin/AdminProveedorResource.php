<?php

namespace App\Http\Resources\Admin;

use App\Http\Resources\Concerns\UrlPublica;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Proveedor (negocio) en las listas del admin. Precargar: user. */
class AdminProveedorResource extends JsonResource
{
    use UrlPublica;

    public function toArray(Request $request): array
    {
        return [
            'id'                       => $this->id,
            'nombre_negocio'           => $this->nombre_restaurante,
            'categoria'                => $this->categoria,
            'municipio'                => $this->municipio,
            'telefono'                 => $this->telefono,
            'rfc'                      => $this->rfc,
            'descripcion'              => $this->descripcion,
            'logo_url'                 => self::urlPublica($this->logo_path),
            'estado'                   => $this->aprobado ? 'aprobado' : ($this->rechazado ? 'rechazado' : 'pendiente'),
            'aprobado'                 => (bool) $this->aprobado,
            'rechazado'                => (bool) $this->rechazado,
            'motivo_rechazo'           => $this->rechazado ? $this->motivo_rechazo : null,
            'activo'                   => (bool) $this->activo,
            'perfil_completo'          => (bool) $this->perfil_completo,
            'solicitado_aprobacion_at' => $this->solicitado_aprobacion_at?->toIso8601String(),
            'citas_count'              => $this->when(isset($this->citas_count), fn () => (int) $this->citas_count),
            'usuario'                  => $this->user ? [
                'id'     => $this->user->id,
                'nombre' => $this->user->name,
                'email'  => $this->user->email,
            ] : null,
            'creado_at'                => $this->created_at?->toIso8601String(),
        ];
    }
}
