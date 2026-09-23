<?php

namespace App\Http\Controllers\Admin\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Categoria;
use App\Services\Admin\CategoriasAdminService;
use Illuminate\Http\Request;
use Inertia\Inertia;

/** La logica vive en CategoriasAdminService (compartido con la API v1). */
class CategoriasController extends Controller
{
    public function __construct(private CategoriasAdminService $categorias) {}

    public function index()
    {
        return Inertia::render('Admin/SuperAdmin/Categorias/Index', [
            'categorias' => $this->categorias->listar(),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate(CategoriasAdminService::reglas());
        $this->categorias->crear($request->nombre);
        return back()->with('success', 'Categoría agregada.');
    }

    public function update(Request $request, Categoria $categoria)
    {
        $request->validate(CategoriasAdminService::reglas($categoria));
        $this->categorias->renombrar($categoria, $request->nombre);
        return back()->with('success', 'Categoría actualizada.');
    }

    public function toggle(Categoria $categoria)
    {
        $this->categorias->alternar($categoria);
        return back()->with('success', $categoria->activo ? 'Categoría activada.' : 'Categoría desactivada.');
    }

    public function destroy(Categoria $categoria)
    {
        $this->categorias->eliminar($categoria);
        return back()->with('success', 'Categoría eliminada.');
    }

    public function reordenar(Request $request)
    {
        // Recibe array de IDs en el nuevo orden
        $request->validate(['ids' => 'required|array']);
        $this->categorias->reordenar($request->ids);
        return back()->with('success', 'Orden actualizado.');
    }
}
