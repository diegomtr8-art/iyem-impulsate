<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Evento;
use App\Models\Restaurantero;
use App\Services\Admin\AdminProveedoresService;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Listado, ficha, edicion, categoria, activar, aprobar, rechazar, alta y
 * baja viven en AdminProveedoresService (compartido con la API v1). Solo la
 * pagina de edicion sigue aqui.
 */
class RestauranteroAdminController extends Controller
{
    public function __construct(private AdminProveedoresService $proveedores) {}

    public function index(Request $request)
    {
        return Inertia::render('Admin/Restauranteros/Index', [
            'restauranteros'        => $this->proveedores->listar($request->search),
            'categorias'            => Restaurantero::categoriasActivas(),
            'pendientesAprobacion'  => $this->proveedores->contarPendientesAprobacion(),
            'proveedoresPendientes' => $this->proveedores->pendientes(),
            'filters'               => $request->only(['search']),
        ]);
    }

    public function store(Request $request)
    {
        $datos = $request->validate(AdminProveedoresService::REGLAS_ALTA);

        // El evento del proveedor nuevo es el del selector del panel (sesion).
        $this->proveedores->crear($datos, Evento::contextoAdmin());

        return redirect()->route('admin.restauranteros.index')
            ->with('success', 'Proveedor creado correctamente.');
    }

    public function show(Restaurantero $restaurantero)
    {
        return Inertia::render('Admin/Restauranteros/Show', array_merge(
            $this->proveedores->detalle($restaurantero),
            ['categorias' => Restaurantero::categoriasActivas()],
        ));
    }

    public function editar(Restaurantero $restaurantero)
    {
        $restaurantero->load('user:id,name,email');

        return Inertia::render('Admin/Restauranteros/Editar', [
            'restaurantero' => $restaurantero,
            'categorias'    => Restaurantero::categoriasActivas(),
        ]);
    }

    public function update(Request $request, Restaurantero $restaurantero)
    {
        $request->validate($this->proveedores->reglasActualizar($restaurantero));

        $this->proveedores->actualizar($restaurantero, $request);

        return back()->with('success', 'Perfil actualizado correctamente.');
    }

    public function updateCategoria(Request $request, Restaurantero $restaurantero)
    {
        $request->validate(['categoria' => 'nullable|string|max:100']);
        $this->proveedores->cambiarCategoria($restaurantero, $request->categoria);

        return back()->with('success', 'Categoría actualizada correctamente.');
    }

    public function toggleActivo(Restaurantero $restaurantero)
    {
        $this->proveedores->alternarActivo($restaurantero);

        return back()->with('success', 'Estado actualizado correctamente.');
    }

    public function destroy(Restaurantero $restaurantero)
    {
        $this->proveedores->eliminar($restaurantero);

        return redirect()->route('admin.restauranteros.index')
            ->with('success', 'Restaurantero eliminado correctamente.');
    }

    public function aprobar(Restaurantero $restaurantero)
    {
        $this->proveedores->aprobar($restaurantero);

        return back()->with('success', 'Proveedor aprobado y notificado.');
    }

    public function rechazarAprobacion(Request $request, Restaurantero $restaurantero)
    {
        $request->validate([
            'motivo' => ['required', 'string', 'max:500'],
        ]);

        $this->proveedores->rechazar($restaurantero, $request->motivo);

        return back()->with('success', 'Perfil rechazado y proveedor notificado.');
    }
}
