<?php

namespace App\Http\Controllers;

use App\Mail\CitaAgendada;
use App\Mail\CitaCancelada;
use App\Models\Cita;
use App\Models\Edicion;
use App\Models\Notificacion;
use App\Models\Restaurantero;
use App\Models\Servicio;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;

class CitaPublicaController extends Controller
{
    public function store(Request $request)
    {
        // El administrador no agenda citas para sí mismo
        if ($request->user()->hasRole('admin')) {
            return back()->withErrors(['error' => 'El administrador no puede agendar citas para sí mismo. Usa el panel de administración para gestionar citas de clientes.']);
        }

        $request->validate([
            'restaurantero_id' => 'required|exists:restauranteros,id',
            'fecha'            => 'required|date|after_or_equal:today',
            'hora'             => 'required|date_format:H:i',
            'notas'            => 'nullable|string|max:1000',
        ]);

        $edicion = Edicion::activa();
        if (!$edicion) {
            return back()->withErrors(['edicion' => 'No hay una edición activa. Contacta al administrador.']);
        }

        // Validar que la fecha esté dentro del rango de agenda de la edición
        $fechaCita = \Carbon\Carbon::parse($request->fecha);
        if ($edicion->fecha_inicio_agenda && $fechaCita->lt($edicion->fecha_inicio_agenda)) {
            return back()->withErrors(['fecha' => 'Las citas de esta edición aún no están abiertas. Inicio de agenda: ' . $edicion->fecha_inicio_agenda->format('d/m/Y') . '.']);
        }
        if ($edicion->fecha_fin_agenda && $fechaCita->gt($edicion->fecha_fin_agenda)) {
            return back()->withErrors(['fecha' => 'La fecha excede el período de agenda de esta edición (hasta ' . $edicion->fecha_fin_agenda->format('d/m/Y') . ').']);
        }

        $totalCitas = $request->user()->citasComoCliente()
            ->where('edicion_id', $edicion->id)
            ->whereNotIn('estado', ['cancelada'])
            ->count();
        if ($totalCitas >= 12) {
            return back()->withErrors(['limit' => 'Has alcanzado el límite de 12 citas en esta edición.']);
        }

        $servicio = Servicio::where('restaurantero_id', $request->restaurantero_id)
            ->where('activo', true)
            ->first();

        if (!$servicio) {
            return back()->withErrors(['servicio' => 'Este restaurantero no tiene servicios disponibles.']);
        }

        $inicio = \Carbon\Carbon::parse($request->fecha . ' ' . $request->hora);
        $fin    = $inicio->copy()->addMinutes($servicio->duracion_minutos ?? 30);

        // Colchón de 10 minutos: el bloque que debe quedar libre alrededor de la nueva cita
        $buffer = 10;
        $inicioConBuffer = $inicio->copy()->subMinutes($buffer);
        $finConBuffer    = $fin->copy()->addMinutes($buffer);

        // Verificar que el CLIENTE no tenga otra cita (con cualquier proveedor) que se solape (incluyendo colchón)
        $conflictoCliente = $request->user()
            ->citasComoCliente()
            ->whereNotIn('estado', ['cancelada'])
            ->where(function ($q) use ($inicioConBuffer, $finConBuffer) {
                $q->where('inicio', '<', $finConBuffer)
                  ->where('fin',    '>', $inicioConBuffer);
            })
            ->first();

        if ($conflictoCliente) {
            $horaConflicto = \Carbon\Carbon::parse($conflictoCliente->inicio)->format('H:i');
            return back()->withErrors(['fecha' => 'Ya tienes una cita agendada a las ' . $horaConflicto . ' (se requieren 10 minutos de separación entre citas).']);
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
                        $q->where('inicio', '<', $fin)
                          ->where('fin',    '>', $inicio);
                    })
                    ->exists();

                if ($conflictoProveedor) {
                    throw new \RuntimeException('SLOT_NO_DISPONIBLE');
                }

                return Cita::create([
                    'edicion_id'       => $edicion->id,
                    'restaurantero_id' => $request->restaurantero_id,
                    'servicio_id'      => $servicio->id,
                    'cliente_id'       => $request->user()->id,
                    'inicio'           => $inicio,
                    'fin'              => $fin,
                    'estado'           => 'pendiente',
                    'notas'            => $request->notas,
                ]);
            });
        } catch (\RuntimeException $e) {
            if ($e->getMessage() === 'SLOT_NO_DISPONIBLE') {
                return back()->withErrors(['fecha' => 'Este horario ya no está disponible con el proveedor. Por favor elige otro.']);
            }
            throw $e;
        }

        $cita->load(['restaurantero.user', 'cliente']);

        try {
            Mail::to($cita->cliente->email)->send(new CitaAgendada($cita, 'cliente'));
            if ($cita->restaurantero->user?->email) {
                Mail::to($cita->restaurantero->user->email)->send(new CitaAgendada($cita, 'proveedor'));
            }
        } catch (\Exception $e) {}

        // Notificaciones en sistema
        $fechaFmt = $cita->inicio->format('d/m/Y H:i');
        Notificacion::crear(
            $cita->cliente_id,
            'cita_nueva',
            'Cita agendada',
            "Tu cita con {$cita->restaurantero->nombre_restaurante} el {$fechaFmt} fue registrada.",
            $cita->id
        );
        if ($proveedorUserId = $cita->restaurantero->user?->id) {
            Notificacion::crear(
                $proveedorUserId,
                'cita_nueva',
                'Nueva cita solicitada',
                "{$cita->cliente->name} agendó una cita contigo para el {$fechaFmt}.",
                $cita->id
            );
        }
        // Notificar a todos los admins
        User::role('admin')->each(fn($a) =>
            Notificacion::crear($a->id, 'cita_nueva', 'Nueva cita en el sistema',
                "{$cita->cliente->name} agendó cita con {$cita->restaurantero->nombre_restaurante} el {$fechaFmt}.", $cita->id)
        );

        return redirect()->route('user.dashboard')
            ->with('success', 'Cita solicitada correctamente.');
    }

    public function destroy(Cita $cita)
    {
        if ($cita->cliente_id !== auth()->id()) {
            abort(403);
        }

        if (in_array($cita->estado, ['completada', 'cancelada'])) {
            return back()->withErrors(['error' => 'No puedes cancelar esta cita.']);
        }

        $cita->load(['restaurantero', 'cliente']);
        $cita->update(['estado' => 'cancelada']);

        try {
            Mail::to($cita->cliente->email)->send(new CitaCancelada($cita));
        } catch (\Exception $e) {
            // No bloqueamos la operación si el correo falla
        }

        return back()->with('success', 'Cita cancelada correctamente.');
    }

    public function dashboard(Request $request)
    {
        $edicion = Edicion::activa();

        $citas = collect();
        $citasCount = 0;

        if ($edicion) {
            $citas = $request->user()
                ->citasComoCliente()
                ->with(['restaurantero', 'servicio'])
                ->where('edicion_id', $edicion->id)
                ->orderByDesc('inicio')
                ->get();

            // Solo cuentan las no canceladas para el límite de 12
            $citasCount = $citas->whereNotIn('estado', ['cancelada'])->count();
        }

        // Ediciones pasadas que tienen citas del usuario
        $edicionesHistorial = Edicion::where('activa', false)
            ->orderByDesc('created_at')
            ->get()
            ->map(function ($ed) use ($request) {
                $ed->mis_citas = $request->user()
                    ->citasComoCliente()
                    ->with(['restaurantero', 'servicio'])
                    ->where('edicion_id', $ed->id)
                    ->orderByDesc('inicio')
                    ->get();
                return $ed;
            })
            ->filter(fn($ed) => $ed->mis_citas->isNotEmpty())
            ->values();

        return Inertia::render('User/Dashboard', [
            'citas'              => $citas,
            'citasCount'         => $citasCount,
            'edicion'            => $edicion,
            'edicionesHistorial' => $edicionesHistorial,
        ]);
    }
}
