<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\CitaCancelada;
use App\Mail\CitaConfirmada;
use App\Models\Cita;
use App\Models\Evento;
use App\Models\Restaurantero;
use App\Models\User;
use App\Services\AgendaCitaService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;

class CitaAdminController extends Controller
{
    public function index(Request $request)
    {
        $query = Cita::with(['restaurantero.user', 'servicio', 'cliente'])
            ->orderByDesc('inicio');

        if ($request->filled('estado')) {
            $query->where('estado', $request->estado);
        }

        if ($request->filled('search')) {
            $query->whereHas('cliente', function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->search . '%')
                  ->orWhere('email', 'like', '%' . $request->search . '%');
            });
        }

        $citas = $query->paginate(20)->withQueryString();

        $clientes = User::role('cliente')->orderBy('name')->get(['id', 'name', 'email']);
        $restauranteros = Restaurantero::where('activo', true)->orderBy('nombre_restaurante')->get(['id', 'nombre_restaurante']);

        return Inertia::render('Admin/Citas/Index', [
            'citas'         => $citas,
            'filters'       => $request->only(['estado', 'search']),
            'clientes'      => $clientes,
            'restauranteros' => $restauranteros,
        ]);
    }

    public function store(Request $request, AgendaCitaService $agenda)
    {
        $request->validate([
            'cliente_id'       => 'required|exists:users,id',
            'restaurantero_id' => 'required|exists:restauranteros,id',
            'fecha'            => 'required|date',
            'hora'             => 'required|date_format:H:i',
            'duracion'         => 'required|integer|min:5|max:480',
            'notas'            => 'nullable|string|max:1000',
        ]);

        $evento = Evento::contextoAdmin();
        if (!$evento) {
            return back()->withErrors(['edicion' => 'No hay un evento activo.']);
        }

        $cliente = User::findOrFail($request->cliente_id);

        // Verificar que el cliente no sea admin
        if ($cliente->hasRole('admin')) {
            return back()->withErrors(['cliente' => 'No se puede agendar una cita para un administrador.']);
        }

        // Cupo, solapes y bloqueo viven en AgendaCitaService. La duracion la
        // fija el admin en el formulario; nace confirmada.
        $agenda->agendar(
            $cliente,
            (int) $request->restaurantero_id,
            $evento,
            Carbon::parse($request->fecha . ' ' . $request->hora),
            (int) $request->duracion,
            ['estado' => 'confirmada', 'notas' => $request->notas, 'origen' => 'admin'],
        );

        return back()->with('success', 'Cita agendada correctamente para ' . $cliente->name . '.');
    }

    public function updateEstado(Request $request, Cita $cita)
    {
        $request->validate([
            'estado' => 'required|in:pendiente,confirmada,cancelada,completada',
        ]);

        $estadoAnterior = $cita->estado;
        $cita->update(['estado' => $request->estado]);

        if (in_array($request->estado, ['confirmada', 'cancelada']) && $estadoAnterior !== $request->estado) {
            $cita->load(['restaurantero', 'cliente']);

            try {
                if ($request->estado === 'confirmada') {
                    Mail::to($cita->cliente->email)->send(new CitaConfirmada($cita));
                } elseif ($request->estado === 'cancelada') {
                    Mail::to($cita->cliente->email)->send(new CitaCancelada($cita));
                }
            } catch (\Exception $e) {
                // No bloqueamos la operación si el correo falla
            }
        }

        return back()->with('success', 'Estado de la cita actualizado.');
    }
}
