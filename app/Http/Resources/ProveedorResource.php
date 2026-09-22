<?php

namespace App\Http\Resources;

use App\Http\Resources\Concerns\UrlPublica;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Ficha publica del proveedor tal como la ve un comprador en
 * Restauranteros/Show.vue: los mismos datos, agrupados por las mismas
 * secciones. Lista blanca: la web oculta la direccion (makeHidden) y no
 * muestra el correo, el motivo de rechazo ni los campos internos.
 */
class ProveedorResource extends JsonResource
{
    use UrlPublica;

    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'nombre_negocio' => $this->nombre_restaurante,
            'categoria'      => $this->categoria,
            'municipio'      => $this->municipio,
            'descripcion'    => $this->descripcion,
            'logo_url'       => self::urlPublica($this->logo_path),
            'contacto'       => [
                'telefono'  => $this->telefono,
                'sitio_web' => $this->sitio_web,
                'rfc'       => $this->rfc,
            ],
            'productos'      => $this->productos(),
            'condiciones_comerciales' => [
                'acepta_credito'          => (bool) $this->acepta_credito,
                'credito_a_negociar'      => (bool) $this->credito_a_negociar,
                'credito_monto_maximo'    => $this->credito_monto_maximo !== null ? (float) $this->credito_monto_maximo : null,
                'credito_tiempo_cantidad' => $this->credito_tiempo_cantidad,
                'credito_tiempo_unidad'   => $this->credito_tiempo_unidad,
                'pago_contraentrega'      => (bool) $this->pago_contraentrega,
                'factura'                 => (bool) $this->factura,
                'regimen_fiscal'          => $this->regimen_fiscal,
            ],
            'logistica'      => [
                'entrega_domicilio' => $this->entrega_domicilio,
                'cobertura_entrega' => $this->cobertura_entrega,
                'forma_entrega'     => $this->forma_entrega,
            ],
            'empresa'        => [
                'razon_social'             => $this->razon_social,
                'nombre_representante'     => $this->nombre_representante,
                'curp_representante'       => $this->curp_representante,
                'rfc'                      => $this->rfc,
                'fecha_inicio_operaciones' => $this->fecha_inicio_operaciones,
                'num_empleados'            => $this->num_empleados,
                'domicilio_en_yucatan'     => $this->domicilio_en_yucatan,
                'redes_sociales'           => $this->redes_sociales ?: [],
            ],
            'producto_servicio' => [
                'mercado_meta'           => $this->mercado_meta,
                'tiempo_vida_anaquel'    => $this->tiempo_vida_anaquel,
                'requisitos_alimentos'   => $this->requisitos_alimentos ?: [],
                // La web solo muestra Si/No segun haya elementos.
                'apoyo_requisitos'       => $this->apoyo_requisitos === null ? null : !empty($this->apoyo_requisitos),
                'requiere_refrigeracion' => $this->requiere_refrigeracion,
                'requiere_congelacion'   => $this->requiere_congelacion,
            ],
        ];
    }

    /** productos_top admite el formato viejo (lista de nombres) y el nuevo (objetos). */
    private function productos(): array
    {
        return collect(is_array($this->productos_top) ? $this->productos_top : [])
            ->map(fn ($p) => is_string($p)
                ? ['nombre' => $p, 'descripcion' => null, 'foto_url' => null, 'capacidad_cantidad' => null, 'capacidad_unidad' => null, 'precio' => null]
                : [
                    'nombre'             => $p['nombre'] ?? null,
                    'descripcion'        => filled($p['descripcion'] ?? null) ? $p['descripcion'] : null,
                    'foto_url'           => self::urlPublica($p['foto_path'] ?? null),
                    'capacidad_cantidad' => $p['capacidad_cantidad'] ?? null,
                    'capacidad_unidad'   => $p['capacidad_unidad'] ?? null,
                    'precio'             => $p['precio'] ?? null,
                ])
            ->filter(fn ($p) => filled($p['nombre']))
            ->values()
            ->all();
    }
}
