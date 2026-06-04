<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\CitaAgendada;
use App\Mail\CitaCancelada;
use App\Mail\CitaConfirmada;
use App\Models\Cita;
use App\Models\Edicion;
use App\Models\Restaurantero;
use App\Models\Servicio;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

    public function store(Request $request)
    {
        $request->validate([
            'cliente_id'       => 'required|exists:users,id',
            'restaurantero_id' => 'required|exists:restauranteros,id',
            'fecha'            => 'required|date',
            'hora'             => 'required|date_format:H:i',
            'notas'            => 'nullable|string|max:1000',
        ]);

        $edicion = Edicion::activa();
        if (!$edicion) {
            return back()->withErrors(['edicion' => 'No hay una edición activa.']);
        }

        $cliente = User::findOrFail($request->cliente_id);

        // Verificar que el cliente no sea admin
        if ($cliente->hasRole('admin')) {
            return back()->withErrors(['cliente' => 'No se puede agendar una cita para un administrador.']);
        }

        $totalCitas = $cliente->citasComoCliente()
            ->where('edicion_id', $edicion->id)
            ->count();
        if ($totalCitas >= 12) {
            return back()->withErrors(['limit' => 'Este cliente ya alcanzó el límite de 12 citas en esta edición.']);
        }

        $servicio = Servicio::where('restaurantero_id', $request->restaurantero_id)
            ->where('activo', true)
            ->first();

        if (!$servicio) {
            return back()->withErrors(['servicio' => 'Este proveedor no tiene servicios disponibles.']);
        }

        $inicio = \Carbon\Carbon::parse($request->fecha . ' ' . $request->hora);
        $fin    = $inicio->copy()->addMinutes($servicio->duracion_minutos ?? 30);

        // Colchón de 10 minutos para el cliente
        $inicioConBuffer = $inicio->copy()->subMinutes(10);
        $finConBuffer    = $fin->copy()->addMinutes(10);

        $conflictoCliente = $cliente->citasComoCliente()
            ->whereNotIn('estado', ['cancelada'])
            ->where(function ($q) use ($inicioConBuffer, $finConBuffer) {
                $q->where('inicio', '<', $finConBuffer)->where('fin', '>', $inicioConBuffer);
            })
            ->first();

        if ($conflictoCliente) {
            $horaConflicto = \Carbon\Carbon::parse($conflictoCliente->inicio)->format('H:i');
            return back()->withErrors(['fecha' => 'Este cliente ya tiene una cita a las ' . $horaConflicto . ' (se requieren 10 minutos de separación entre citas).']);
        }

        // Atomic booking: lock provider row to prevent concurrent double-bookings for the same slot
        try {
            $cita = DB::transaction(function () use ($request, $edicion, $servicio, $inicio, $fin) {
                // Acquiring a write lock on the provider row serializes concurrent requests
                // for this provider. The second request waits here until the first commits.
                Restaurantero::where('id', $request->restaurantero_id)->lockForUpdate()->first();

                $conflictoProveedor = Cita::where('restaurantero_id', $request->restaurantero_id)
                    ->whereNotIn('estado', ['cancelada'])
                    ->where(function ($q) use ($inicio, $fin) {
                        $q->where('inicio', '<', $fin)->where('fin', '>', $inicio);
                    })
                    ->exists();

                if ($conflictoProveedor) {
                    throw new \RuntimeException('SLOT_NO_DISPONIBLE');
                }

                return Cita::create([
                    'edicion_id'       => $edicion->id,
                    'restaurantero_id' => $request->restaurantero_id,
                    'servicio_id'      => $servicio->id,
                    'cliente_id'       => $request->cliente_id,
                    'inicio'           => $inicio,
                    'fin'              => $fin,
                    'estado'           => 'confirmada',
                    'notas'            => $request->notas,
                ]);
            });
        } catch (\RuntimeException $e) {
            if ($e->getMessage() === 'SLOT_NO_DISPONIBLE') {
                return back()->withErrors(['fecha' => 'El proveedor ya tiene una cita en ese horario. Elige otro.']);
            }
            throw $e;
        }

        $cita->load(['restaurantero.user', 'cliente']);

        try {
            Mail::to($cita->cliente->email)->send(new CitaAgendada($cita, 'cliente'));
        } catch (\Exception $e) {
            // El correo no bloquea la operación
        }

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
