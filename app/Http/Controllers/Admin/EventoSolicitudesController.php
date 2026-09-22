<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Evento;
use App\Models\Restaurantero;
use App\Models\User;
use App\Services\Admin\SolicitudesEventoService;
use Illuminate\Http\Request;
use Inertia\Inertia;

/** La logica vive en SolicitudesEventoService (compartido con la API v1). */
class EventoSolicitudesController extends Controller
{
    public function __construct(private SolicitudesEventoService $solicitudes) {}

    public function index(Evento $evento)
    {
        return Inertia::render('Admin/Eventos/Solicitudes', $this->solicitudes->datosPagina($evento));
    }

    public function aprobar(Evento $evento, User $user, Request $request)
    {
        $request->validate([
            'tipo' => 'required|in:proveedor,comprador,expositor',
        ]);

        $this->solicitudes->aprobar($evento, $user, $request->tipo);

        return back()->with('success', 'Solicitud aprobada.');
    }

    public function rechazar(Evento $evento, User $user, Request $request)
    {
        $request->validate([
            'tipo'           => 'required|in:proveedor,comprador,expositor',
            'motivo_rechazo' => 'required|string|max:500',
        ]);

        $this->solicitudes->rechazar($evento, $user, $request->tipo, $request->motivo_rechazo);

        return back()->with('success', 'Solicitud rechazada.');
    }

    public function aprobarTodos(Evento $evento, Request $request)
    {
        $request->validate(['tipo' => 'required|in:comprador,proveedor,expositor']);

        $total = $this->solicitudes->aprobarTodos($evento, $request->tipo);

        if ($total === 0) {
            return back()->with('success', 'No hay solicitudes pendientes de ese tipo.');
        }

        return back()->with('success', "{$total} solicitudes aprobadas.");
    }

    public function revertirPendiente(Evento $evento, User $user, Request $request)
    {
        $request->validate([
            'tipo' => 'required|in:proveedor,comprador,expositor',
        ]);

        $this->solicitudes->revertirPendiente($evento, $user, $request->tipo);

        return back()->with('success', 'Solicitud regresada a pendiente.');
    }

    public function agregarProveedor(Evento $evento, Request $request)
    {
        $request->validate([
            'restaurantero_id' => 'required|exists:restauranteros,id',
        ]);

        $restaurantero = Restaurantero::findOrFail($request->restaurantero_id);
        $this->solicitudes->agregarProveedor($evento, $restaurantero);

        return back()->with('success', 'Proveedor "' . $restaurantero->nombre_restaurante . '" agregado al evento correctamente.');
    }

    public function agregarComprador(Evento $evento, Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);

        $user = User::findOrFail($request->user_id);
        $this->solicitudes->agregarComprador($evento, $user);

        return back()->with('success', 'Comprador "' . ($user->nombre_empresa ?? $user->name) . '" agregado al evento correctamente.');
    }

    public function eliminar(Evento $evento, User $user, Request $request)
    {
        $request->validate([
            'tipo'               => 'required|in:proveedor,comprador,expositor',
            'motivo_eliminacion' => 'required|string|max:500',
        ]);

        $this->solicitudes->eliminar($evento, $user, $request->tipo, $request->motivo_eliminacion);

        return back()->with('success', 'Usuario eliminado del evento.');
    }
}
