<?php

namespace App\Services;

use App\Models\Evento;
use App\Models\Restaurantero;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Directorio de proveedores: que proveedores se ven, como se filtran y como se
 * ordenan por coincidencia con las "necesidades" del comprador. Lo usan
 * RestauranteroPublicoController (/proveedores), CitaPublicaController
 * (/mi-panel) y la API v1 (/proveedores).
 */
class DirectorioProveedoresService
{
    /** Encuentros de negocios activos, el destacado primero (orden de Evento::queryActivos). */
    public function encuentrosActivos(): Collection
    {
        return Evento::queryActivos()
            ->where('tipo_evento', 'encuentro_negocios')
            ->get();
    }

    /** Perfil activo y aprobado: el minimo para aparecer en cualquier listado publico. */
    public function visibles(): Builder
    {
        return Restaurantero::query()
            ->where('activo', true)
            ->where('aprobado', true);
    }

    /** Directorio general de la plataforma (sin evento): ademas, perfil completo. */
    public function dePlataforma(): Builder
    {
        return $this->visibles()->where('perfil_completo', true);
    }

    /**
     * Proveedores APROBADOS como proveedor en el evento (o en cualquiera de los
     * eventos si se pasa una lista de ids).
     *
     * @param Evento|iterable<int> $eventos
     */
    public function delEvento(Evento|iterable $eventos): Builder
    {
        $ids = $eventos instanceof Evento ? [$eventos->id] : collect($eventos)->all();

        return $this->visibles()
            ->whereHas('user', function ($q) use ($ids) {
                $q->whereExists(function ($sub) use ($ids) {
                    $sub->from('evento_usuario')
                        ->whereColumn('evento_usuario.user_id', 'users.id')
                        ->whereIn('evento_usuario.evento_id', $ids)
                        ->where('evento_usuario.tipo', 'proveedor')
                        ->where('evento_usuario.estado', 'aprobado');
                });
            });
    }

    /** Busqueda (nombre, direccion, descripcion, municipio) y categoria exacta. */
    public function aplicarFiltros(Builder $query, ?string $search, ?string $categoria): Builder
    {
        if (filled($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('nombre_restaurante', 'like', "%{$search}%")
                  ->orWhere('direccion', 'like', "%{$search}%")
                  ->orWhere('descripcion', 'like', "%{$search}%")
                  ->orWhere('municipio', 'like', "%{$search}%");
            });
        }

        if (filled($categoria)) {
            $query->where('categoria', $categoria);
        }

        return $query;
    }

    /** Categorias presentes en un listado, para el filtro. */
    public function categorias(Builder $query): Collection
    {
        return (clone $query)
            ->whereNotNull('categoria')
            ->distinct()
            ->orderBy('categoria')
            ->pluck('categoria');
    }

    /** Palabras de 3+ letras de las necesidades del comprador (maximo 15). */
    public function palabrasClave(?string $necesidades): Collection
    {
        return collect(preg_split('/[\s,;\.]+/', mb_strtolower($necesidades ?? '')))
            ->filter(fn ($p) => mb_strlen($p) >= 3)
            ->unique()
            ->take(15)
            ->values();
    }

    /** Cuantas palabras clave aparecen en nombre, descripcion, categoria y productos. */
    public function coincidencias(Restaurantero $p, Collection $palabras): int
    {
        if ($palabras->isEmpty()) {
            return 0;
        }

        $productos = is_array($p->productos_top) ? $p->productos_top : [];
        $texto = mb_strtolower(
            ($p->nombre_restaurante ?? '') . ' ' .
            ($p->descripcion ?? '') . ' ' .
            ($p->categoria ?? '') . ' ' .
            implode(' ', array_column($productos, 'nombre')) . ' ' .
            implode(' ', array_filter($productos, 'is_string'))
        );

        return $palabras->filter(fn ($kw) => str_contains($texto, $kw))->count();
    }

    /** Ordena de mayor a menor coincidencia (orden estable para los empates). */
    public function ordenarPorCoincidencia(Collection $proveedores, Collection $palabras): Collection
    {
        return $proveedores->sortByDesc(fn ($p) => $this->coincidencias($p, $palabras));
    }

    /** Misma regla de siempre: un perfil inactivo solo lo ven su dueno y el admin. */
    public function puedeVer(?User $user, Restaurantero $restaurantero): bool
    {
        return $restaurantero->activo
            || ($user && $user->id === $restaurantero->user_id)
            || ($user && $user->hasRole('admin'));
    }

    /** Visita a la ficha publica, para las metricas del admin. */
    public function registrarVisita(Restaurantero $restaurantero, Request $request): void
    {
        $ua = strtolower($request->userAgent() ?? '');
        $device = match (true) {
            str_contains($ua, 'mobile') || str_contains($ua, 'android') => 'mobile',
            str_contains($ua, 'tablet') || str_contains($ua, 'ipad')   => 'tablet',
            default => 'desktop',
        };

        DB::table('page_visits')->insert([
            'restaurantero_id' => $restaurantero->id,
            'url'              => '/restauranteros/' . $restaurantero->id,
            'device_type'      => $device,
            'ip'               => $request->ip(),
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);
    }
}
