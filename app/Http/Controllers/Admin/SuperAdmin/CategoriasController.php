<?php

namespace App\Http\Controllers\Admin\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Categoria;
use App\Models\Restaurantero;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CategoriasController extends Controller
{
    public function index()
    {
        return Inertia::render('Admin/SuperAdmin/Categorias/Index', [
            'categorias' => Categoria::orderBy('orden')
                ->withCount(['restauranteros' => fn ($q) => $q->where('activo', true)])
                ->get(),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate(['nombre' => 'required|string|max:100|unique:categorias,nombre']);
        $maxOrden = Categoria::max('orden') ?? 0;
        Categoria::create(['nombre' => $request->nombre, 'orden' => $maxOrden + 1, 'activo' => true]);
        return back()->with('success', 'Categoría agregada.');
    }

    public function update(Request $request, Categoria $categoria)
    {
        $request->validate(['nombre' => 'required|string|max:100|unique:categorias,nombre,' . $categoria->id]);
        $categoria->update(['nombre' => $request->nombre]);
        return back()->with('success', 'Categoría actualizada.');
    }

    public function toggle(Categoria $categoria)
    {
        $categoria->update(['activo' => !$categoria->activo]);
        return back()->with('success', $categoria->activo ? 'Categoría activada.' : 'Categoría desactivada.');
    }

    public function destroy(Categoria $categoria)
    {
        // Solo borrar si no está en uso
        $enUso = Restaurantero::where('categoria', $categoria->nombre)->exists();
        if ($enUso) {
            return back()->withErrors(['error' => 'No se puede eliminar: hay proveedores con esta categoría.']);
        }
        $categoria->delete();
        return back()->with('success', 'Categoría eliminada.');
    }

    public function reordenar(Request $request)
    {
        // Recibe array de IDs en el nuevo orden
        $request->validate(['ids' => 'required|array']);
        foreach ($request->ids as $i => $id) {
            Categoria::where('id', $id)->update(['orden' => $i]);
        }
        return back()->with('success', 'Orden actualizado.');
    }
}
