<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Evento;
use App\Models\Horario;
use App\Models\Restaurantero;
use App\Models\Servicio;
use App\Models\User;
use App\Services\Admin\AdminProveedoresService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;

/**
 * Listado, ficha, edicion, categoria, activar, aprobar y rechazar viven en
 * AdminProveedoresService (compartido con la API v1). Alta (store), pagina de
 * edicion y baja siguen aqui.
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
        $request->validate([
            'name'               => 'required|string|max:255',
            'email'              => 'required|email|unique:users,email',
            'password'           => 'required|string|min:8',
            'nombre_restaurante' => 'required|string|max:255',
            'telefono'           => 'nullable|string|max:30',
            'direccion'          => 'nullable|string|max:255',
            'municipio'          => 'nullable|string|max:100',
            'categoria'          => 'nullable|string|max:100',
            'descripcion'        => 'nullable|string|max:1000',
        ]);

        $user = User::create([
            'name'     => $request->name,
            'email'    => $request->email,
            'password' => Hash::make($request->password),
        ]);
        $user->assignRole('restaurantero');

        $restaurantero = Restaurantero::create([
            'user_id'            => $user->id,
            'edicion_id'         => Evento::contextoAdmin()?->id,
            'nombre_restaurante' => $request->nombre_restaurante,
            'telefono'           => $request->telefono,
            'direccion'          => $request->direccion,
            'municipio'          => $request->municipio,
            'categoria'          => $request->categoria,
            'descripcion'        => $request->descripcion,
            'activo'             => true,
        ]);

        Evento::registrarProveedorEnEventoActivo($user->id, true);

        Servicio::create([
            'restaurantero_id' => $restaurantero->id,
            'nombre'           => 'Mesa de Networking',
            'duracion_minutos' => 30,
            'precio'           => 0,
            'activo'           => true,
        ]);

        for ($dia = 1; $dia <= 5; $dia++) {
            Horario::create([
                'restaurantero_id' => $restaurantero->id,
                'dia_semana'       => $dia,
                'hora_inicio'      => '09:00:00',
                'hora_fin'         => '16:00:00',
                'activo'           => true,
            ]);
        }

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
        $user = $restaurantero->user;
        $restaurantero->delete();
        $user->delete();

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
