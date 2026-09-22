<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Cuenta vista por el admin / super-admin. Lista blanca: nunca password,
 * tokens, two_factor_* ni rutas de INE/CSF (solo tiene_ine / tiene_csf).
 */
class AdminUsuarioResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                     => $this->id,
            'nombre'                 => $this->name,
            'email'                  => $this->email,
            'email_verificado'       => $this->email_verified_at !== null,
            'telefono'               => $this->telefono,
            'curp'                   => $this->curp,
            'rfc'                    => $this->rfc,
            'municipio'              => $this->municipio,
            'nombre_empresa'         => $this->nombre_empresa,
            'sitio_web'              => $this->sitio_web,
            'camara_asociacion'      => $this->camara_asociacion,
            'nombre_establecimiento' => $this->nombre_establecimiento,
            'necesidades'            => $this->necesidades,
            'rol_activo'             => $this->active_role,
            'roles'                  => $this->getRoleNames()->values()->all(),
            'perfil_completo'        => (bool) $this->perfil_completo,
            'tiene_ine'              => !empty($this->ine_path),
            'tiene_csf'              => !empty($this->csf_path),
            'csf_fecha'              => $this->csf_fecha ? \Carbon\Carbon::parse($this->csf_fecha)->toDateString() : null,
            'citas_count'            => $this->when(isset($this->citas_count), fn () => (int) $this->citas_count),
            'registrado_at'          => $this->created_at?->toIso8601String(),
        ];
    }
}
