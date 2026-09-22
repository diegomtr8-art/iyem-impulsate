<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Unico punto donde se decide que datos del usuario salen por la API.
 * NUNCA: ine_path, csf_path, password, remember_token, two_factor_*.
 * Los documentos viajan como booleanos.
 */
class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $onboarding = $this->onboarding();

        return [
            'id'              => $this->id,
            'name'            => $this->name,
            'email'           => $this->email,
            'email_verificado'=> !is_null($this->email_verified_at),
            'telefono'        => $this->telefono,
            'municipio'       => $this->municipio,
            'nombre_empresa'  => $this->nombre_empresa,
            'rol_activo'      => $onboarding['rol_activo'],
            'roles'           => $this->getRoleNames()->values(),
            'perfil_completo' => $onboarding['perfil_completo'],
            'es_comprador'    => $this->hasRole('cliente'),
            'es_proveedor'    => $this->hasRole('restaurantero'),
            'tiene_dual_rol'  => $this->tieneDualRol(),
            // Perfil de NEGOCIO (Restaurantero): null si no es proveedor.
            'perfil_proveedor_completo' => $this->hasRole('restaurantero')
                ? (bool) $this->restaurantero?->perfil_completo
                : null,
            'tiene_ine'       => !empty($this->ine_path),
            'tiene_csf'       => !empty($this->csf_path),
            'csf_fecha'       => $this->csf_fecha,
            'foto_url'        => $this->profile_photo_path ? $this->profile_photo_url : null,
        ];
    }
}
