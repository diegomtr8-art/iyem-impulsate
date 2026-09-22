<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Datos del comprador que edita la web en /user/profile (formComprador de
 * Perfil/Show.vue) y /completar-perfil. Sin rutas de documentos.
 */
class PerfilCompradorResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'name'                   => $this->name,
            'email'                  => $this->email,
            'telefono'               => $this->telefono,
            'curp'                   => $this->curp,
            'rfc'                    => $this->rfc,
            'genero'                 => $this->genero,
            'municipio'              => $this->municipio,
            'nombre_empresa'         => $this->nombre_empresa,
            'sitio_web'              => $this->sitio_web,
            'necesidades'            => $this->necesidades,
            'camara_asociacion'      => $this->camara_asociacion,
            'nombre_establecimiento' => $this->nombre_establecimiento,
            'es_restaurantero'       => (bool) $this->es_restaurantero,
            'perfil_completo'        => (bool) $this->perfil_completo,
            'documentos'             => new DocumentosUsuarioResource($this->resource),
        ];
    }
}
