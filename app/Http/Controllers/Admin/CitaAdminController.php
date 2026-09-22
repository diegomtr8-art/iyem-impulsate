<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Cita;
use App\Models\Evento;
use App\Services\Admin\AdminCitasService;
use Illuminate\Http\Request;
use Inertia\Inertia;

/** La logica vive en AdminCitasService (compartido con la API v1). */
class CitaAdminController extends Controller
{
    public function __construct(private AdminCitasService $citas) {}

    public function index(Request $request)
    {
        return Inertia::render('Admin/Citas/Index', [
            'citas'          => $this->citas->listar($request->only(['estado', 'search'])),
            'filters'        => $request->only(['estado', 'search']),
            'clientes'       => $this->citas->clientes(),
            'restauranteros' => $this->citas->proveedores(),
        ]);
    }

    public function store(Request $request)
    {
        $datos = $request->validate(AdminCitasService::REGLAS_ALTA);

        // El evento de la cita es el del selector del panel (sesion).
        $cita = $this->citas->crear(Evento::contextoAdmin(), $datos);

        return back()->with('success', 'Cita agendada correctamente para ' . $cita->cliente->name . '.');
    }

    public function updateEstado(Request $request, Cita $cita)
    {
        $request->validate([
            'estado' => 'required|in:pendiente,confirmada,cancelada,completada',
        ]);

        $this->citas->cambiarEstado($cita, $request->estado);

        return back()->with('success', 'Estado de la cita actualizado.');
    }
}
