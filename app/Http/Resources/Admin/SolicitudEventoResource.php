<?php

namespace App\Http\Resources\Admin;

use App\Http\Resources\Concerns\UrlPublica;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Solicitud de registro a un evento (fila de evento_usuario) tal como la arma
 * SolicitudesEventoService::registros(). $this->resource es un array.
 * Lista blanca: ni rutas de INE/CSF (solo tiene_ine/tiene_csf) ni rutas de
 * disco del logo (solo URL).
 */
class SolicitudEventoResource extends JsonResource
{
    use UrlPublica;

    public function toArray(Request $request): array
    {
        $s = $this->resource;
        $u = $s['user'];
        $r = $s['restaurantero'];
        $f = fn ($v) => $v ? Carbon::parse($v)->toIso8601String() : null;

        return [
            'id'             => $s['id'],
            'user_id'        => $s['user_id'],
            'tipo'           => $s['tipo'],
            'estado'         => $s['estado'],
            'motivo_rechazo' => $s['motivo_rechazo'],
            'respondido_at'  => $f($s['respondido_at']),
            'solicitado_at'  => $f($s['created_at']),
            'citas_activas'  => (int) $s['citas_activas'],
            'usuario'        => $u ? [
                'id'                     => $u['id'],
                'nombre'                 => $u['name'],
                'email'                  => $u['email'],
                'telefono'               => $u['telefono'],
                'sitio_web'              => $u['sitio_web'],
                'necesidades'            => $u['necesidades'],
                'rfc'                    => $u['rfc'],
                'nombre_empresa'         => $u['nombre_empresa'],
                'camara_asociacion'      => $u['camara_asociacion'],
                'nombre_establecimiento' => $u['nombre_establecimiento'],
                'registrado_at'          => $f($u['created_at']),
                'citas_historico'        => (int) $u['citas_historico'],
                'tiene_ine'              => (bool) $u['tiene_ine'],
                'tiene_csf'              => (bool) $u['tiene_csf'],
                'csf_fecha'              => $u['csf_fecha'] ? Carbon::parse($u['csf_fecha'])->toDateString() : null,
            ] : null,
            'negocio'        => $r ? [
                'id'                      => $r['id'],
                'nombre_negocio'          => $r['nombre_restaurante'],
                'descripcion'             => $r['descripcion'],
                'categoria'               => $r['categoria'],
                'municipio'               => $r['municipio'],
                'rfc'                     => $r['rfc'],
                'domicilio_en_yucatan'    => $r['domicilio_en_yucatan'],
                'telefono'                => $r['telefono'],
                'sitio_web'               => $r['sitio_web'],
                'redes_sociales'          => $r['redes_sociales'],
                'logo_url'                => self::urlPublica($r['logo_path']),
                'activo'                  => (bool) $r['activo'],
                'aprobado'                => (bool) $r['aprobado'],
                'perfil_completo'         => (bool) $r['perfil_completo'],
                'acepta_credito'          => (bool) $r['acepta_credito'],
                'credito_monto_maximo'    => $r['credito_monto_maximo'] !== null ? (float) $r['credito_monto_maximo'] : null,
                'credito_tiempo_cantidad' => $r['credito_tiempo_cantidad'],
                'credito_tiempo_unidad'   => $r['credito_tiempo_unidad'],
                'pago_contraentrega'      => (bool) $r['pago_contraentrega'],
                'factura'                 => (bool) $r['factura'],
                'regimen_fiscal'          => $r['regimen_fiscal'],
                'entrega_domicilio'       => $r['entrega_domicilio'] === null ? null : (bool) $r['entrega_domicilio'],
                'cobertura_entrega'       => $r['cobertura_entrega'],
                'forma_entrega'           => $r['forma_entrega'],
                'productos'               => collect(is_array($r['productos_top']) ? $r['productos_top'] : [])->values()->map(fn ($p) => is_string($p)
                    ? ['nombre' => $p, 'descripcion' => null, 'foto_url' => null, 'capacidad_cantidad' => null, 'capacidad_unidad' => null]
                    : [
                        'nombre'             => $p['nombre'] ?? null,
                        'descripcion'        => filled($p['descripcion'] ?? null) ? $p['descripcion'] : null,
                        'foto_url'           => self::urlPublica($p['foto_path'] ?? null),
                        'capacidad_cantidad' => isset($p['capacidad_cantidad']) ? (float) $p['capacidad_cantidad'] : null,
                        'capacidad_unidad'   => $p['capacidad_unidad'] ?? null,
                    ])->all(),
                'citas_count'             => (int) $r['citas_count'],
            ] : null,
        ];
    }
}
