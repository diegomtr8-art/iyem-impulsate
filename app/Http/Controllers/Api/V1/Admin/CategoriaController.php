<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\CategoriaResource;
use App\Models\Categoria;
use App\Services\Admin\CategoriasAdminService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/** Categorias de proveedor SOLO super-admin (web: /admin/categorias). */
class CategoriaController extends Controller
{
    public function __construct(private CategoriasAdminService $categorias) {}

    public function index()
    {
        Gate::authorize('super-admin');
        $lista = $this->categorias->listar();

        return CategoriaResource::collection($lista)->additional(['meta' => ['total' => $lista->count()]]);
    }

    public function store(Request $request)
    {
        Gate::authorize('super-admin');
        $d = $request->validate(CategoriasAdminService::reglas());

        return (new CategoriaResource($this->categorias->crear($d['nombre'])->fresh()))
            ->additional(['message' => 'Categoría agregada.'])
            ->response()->setStatusCode(201);
    }

    public function update(Request $request, Categoria $categoria)
    {
        Gate::authorize('super-admin');
        $d = $request->validate(CategoriasAdminService::reglas($categoria));

        return (new CategoriaResource($this->categorias->renombrar($categoria, $d['nombre'])))
            ->additional(['message' => 'Categoría actualizada.']);
    }

    /** Activa / desactiva (web: toggle). */
    public function activo(Categoria $categoria)
    {
        Gate::authorize('super-admin');
        $this->categorias->alternar($categoria);

        return (new CategoriaResource($categoria))
            ->additional(['message' => $categoria->activo ? 'Categoría activada.' : 'Categoría desactivada.']);
    }

    public function destroy(Categoria $categoria)
    {
        Gate::authorize('super-admin');
        $this->categorias->eliminar($categoria);

        return response()->json(['data' => null, 'message' => 'Categoría eliminada.']);
    }

    /** ids en el nuevo orden (el primero queda en 0). Devuelve la lista ya ordenada. */
    public function reordenar(Request $request)
    {
        Gate::authorize('super-admin');
        $d = $request->validate(['ids' => 'required|array', 'ids.*' => 'integer']);
        $this->categorias->reordenar($d['ids']);

        return CategoriaResource::collection($this->categorias->listar())->additional(['message' => 'Orden actualizado.']);
    }
}
