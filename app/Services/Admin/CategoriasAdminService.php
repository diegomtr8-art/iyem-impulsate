<?php

namespace App\Services\Admin;

use App\Exceptions\ReglaDeNegocioException;
use App\Models\Categoria;
use App\Models\Restaurantero;
use Illuminate\Support\Collection;

/**
 * Categorias de proveedor (solo super-admin): lista con proveedores activos
 * por categoria, alta al final del orden, renombrar, activar/desactivar,
 * baja (solo si ningun proveedor la usa) y reordenar. Lo usan
 * SuperAdmin\CategoriasController (web) y la API v1.
 */
class CategoriasAdminService
{
    public function listar(): Collection
    {
        return Categoria::orderBy('orden')
            ->withCount(['restauranteros' => fn ($q) => $q->where('activo', true)])
            ->get();
    }

    public static function reglas(?Categoria $categoria = null): array
    {
        return ['nombre' => 'required|string|max:100|unique:categorias,nombre' . ($categoria ? ',' . $categoria->id : '')];
    }

    public function crear(string $nombre): Categoria
    {
        $maxOrden = Categoria::max('orden') ?? 0;

        return Categoria::create(['nombre' => $nombre, 'orden' => $maxOrden + 1, 'activo' => true]);
    }

    /**
     * Solo cambia el nombre de la categoria. Los proveedores guardan la
     * categoria como TEXTO, asi que los que tenian el nombre anterior no se
     * actualizan (comportamiento de la web; ver reporte de la fase 4B).
     */
    public function renombrar(Categoria $categoria, string $nombre): Categoria
    {
        $categoria->update(['nombre' => $nombre]);

        return $categoria;
    }

    public function alternar(Categoria $categoria): Categoria
    {
        $categoria->update(['activo' => !$categoria->activo]);

        return $categoria;
    }

    public function eliminar(Categoria $categoria): void
    {
        // Solo borrar si no está en uso
        if (Restaurantero::where('categoria', $categoria->nombre)->exists()) {
            throw new ReglaDeNegocioException('No se puede eliminar: hay proveedores con esta categoría.');
        }
        $categoria->delete();
    }

    /** $ids en el nuevo orden: el primero queda con orden 0. */
    public function reordenar(array $ids): void
    {
        foreach ($ids as $i => $id) {
            Categoria::where('id', $id)->update(['orden' => $i]);
        }
    }
}
