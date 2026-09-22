<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AgendaPropuesta;
use App\Models\Evento;
use App\Services\Admin\AgendaAdminService;
use Illuminate\Http\Request;
use Inertia\Inertia;

/** La logica vive en AgendaAdminService (compartido con la API v1). */
class AgendaController extends Controller
{
    public function __construct(private AgendaAdminService $agenda) {}

    public function index()
    {
        return Inertia::render('Admin/Agenda/Index', [
            'propuestas' => $this->agenda->listar(),
        ]);
    }

    public function crear()
    {
        $evento = Evento::contextoAdmin();

        if (!$evento) {
            return redirect()->route('admin.agenda.index')
                ->with('error', 'No hay un evento activo para crear agendas.');
        }

        return Inertia::render('Admin/Agenda/Crear', [
            'evento'      => $evento->only(['id', 'nombre']),
            'compradores' => $this->agenda->compradores($evento),
            'proveedores' => $this->agenda->proveedores($evento),
            'slots'       => $this->agenda->slots($evento),
        ]);
    }

    public function store(Request $request)
    {
        $datos = $request->validate(AgendaAdminService::REGLAS_ALTA);
        $datos['enviar'] = $request->boolean('enviar');

        $this->agenda->crear(Evento::contextoAdmin(), $datos, $request->user());

        return redirect()->route('admin.agenda.index')
            ->with('success', 'Propuesta de agenda guardada' .
                ($datos['enviar'] ? ' y enviada al comprador.' : '.'));
    }

    public function show(AgendaPropuesta $agenda)
    {
        $agenda->load([
            'comprador:id,name,nombre_empresa,email',
            'evento:id,nombre,fecha_hora_inicio',
            'citas.proveedor:id,nombre_restaurante,logo_path',
        ]);

        return Inertia::render('Admin/Agenda/Show', [
            'propuesta'        => $agenda,
            'citasConfirmadas' => $this->agenda->citasConfirmadas($agenda),
        ]);
    }

    public function enviar(AgendaPropuesta $agenda)
    {
        $this->agenda->enviar($agenda);

        return back()->with('success', 'Correo enviado al comprador.');
    }

    public function destroy(AgendaPropuesta $agenda)
    {
        $mensaje = $this->agenda->eliminar($agenda);

        return redirect()->route('admin.agenda.index')->with('success', $mensaje);
    }
}
