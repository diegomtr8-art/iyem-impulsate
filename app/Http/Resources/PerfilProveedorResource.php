<?php

namespace App\Http\Resources;

use App\Http\Resources\Concerns\UrlPublica;
use App\Models\Restaurantero;
use App\Services\PerfilProveedorService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Perfil de NEGOCIO del proveedor para su propio formulario (el de la seccion
 * "Proveedor" de /user/profile), agrupado por las mismas secciones. Los valores
 * son los que la web precarga (PerfilProveedorService::valoresFormulario).
 * Estado, porcentaje y faltantes los decide el servidor.
 *
 * Nunca rutas de disco: logo y fotos van como URL absoluta.
 *
 * $this->resource = el User (proveedor).
 */
class PerfilProveedorResource extends JsonResource
{
    use UrlPublica;

    public function toArray(Request $request): array
    {
        $servicio  = app(PerfilProveedorService::class);
        $r         = $this->resource->restaurantero;
        $valores   = $servicio->valoresFormulario($this->resource, $r);
        $faltantes = $servicio->faltantes($r);

        $secciones = [];
        foreach (PerfilProveedorService::SECCIONES as $clave => $seccion) {
            $secciones[$clave] = collect($seccion['campos'])
                ->mapWithKeys(fn ($campo) => [$campo => $this->normalizar($campo, $valores[$campo] ?? null)])
                ->all();
        }

        return [
            'negocio'   => self::negocio($r, $servicio),
            'faltantes' => $faltantes,
            'secciones' => collect(PerfilProveedorService::SECCIONES)->map(fn ($s, $clave) => [
                'clave'     => $clave,
                'etiqueta'  => $s['etiqueta'],
                'completa'  => !collect($faltantes)->contains('seccion', $clave),
                'faltantes' => collect($faltantes)->where('seccion', $clave)->pluck('campo')->values()->all(),
            ])->values()->all(),
            'valores'   => $secciones,
            'logo_url'  => self::urlPublica($r?->logo_path),
            'productos' => self::productos($r),
        ];
    }

    /** Estado del negocio (panel, perfil, inicio). */
    public static function negocio(?Restaurantero $r, ?PerfilProveedorService $servicio = null): array
    {
        $servicio ??= app(PerfilProveedorService::class);

        return [
            'id'              => $r?->id,
            'nombre_negocio'  => $r?->nombre_restaurante,
            'estado'          => $servicio->estado($r),
            'motivo_rechazo'  => $r?->rechazado ? $r->motivo_rechazo : null,
            'activo'          => (bool) $r?->activo,
            'visible_en_directorio' => (bool) ($r?->activo && $r?->aprobado),
            'perfil_completo' => (bool) $r?->perfil_completo,
            'porcentaje'      => $servicio->porcentaje($r),
            'logo_url'        => self::urlPublica($r?->logo_path),
            'solicitado_aprobacion_at' => $r?->solicitado_aprobacion_at?->toIso8601String(),
        ];
    }

    /** Productos con su posicion (indice) para editarlos/borrarlos por /proveedor/productos/{indice}. */
    public static function productos(?Restaurantero $r): array
    {
        return collect(is_array($r?->productos_top) ? $r->productos_top : [])
            ->values()
            ->map(fn ($p, $i) => is_string($p)
                ? ['indice' => $i, 'nombre' => $p, 'descripcion' => null, 'foto_url' => null, 'capacidad_cantidad' => null, 'capacidad_unidad' => null]
                : [
                    'indice'             => $i,
                    'nombre'             => $p['nombre'] ?? null,
                    'descripcion'        => filled($p['descripcion'] ?? null) ? $p['descripcion'] : null,
                    'foto_url'           => self::urlPublica($p['foto_path'] ?? null),
                    'capacidad_cantidad' => isset($p['capacidad_cantidad']) ? (float) $p['capacidad_cantidad'] : null,
                    'capacidad_unidad'   => $p['capacidad_unidad'] ?? null,
                ])
            ->all();
    }

    /** '' del formulario web -> null; montos como numero. */
    private function normalizar(string $campo, $valor)
    {
        if ($valor === '') {
            return null;
        }
        if ($campo === 'credito_monto_maximo' && $valor !== null) {
            return (float) $valor;
        }
        if (in_array($campo, ['num_empleados', 'credito_tiempo_cantidad']) && $valor !== null) {
            return (int) $valor;
        }

        return $valor;
    }
}
