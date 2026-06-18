<?php

namespace App\Http\Controllers;

use App\Mail\CitaAgendada;
use App\Mail\CitaCancelada;
use App\Models\Cita;
use App\Models\Evento;
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
            'restaurantero_id'    => 'required|exists:restauranteros,id',
            'fecha'               => 'required|date|after_or_equal:today',
            'hora'                => 'required|date_format:H:i',
            'notas'               => 'nullable|string|max:1000',
            'productos_interes'   => 'nullable|array',
            'productos_interes.*' => 'string|max:255',
        ]);

        $evento = Evento::activo();
        if (!$evento) {
            return back()->withErrors(['edicion' => 'No hay un evento activo. Contacta al administrador.']);
        }

        // Verificar que el comprador esté aprobado en el evento activo
        $registroEvento = DB::table('evento_usuario')
            ->where('evento_id', $evento->id)
            ->where('user_id', $request->user()->id)
            ->where('tipo', 'comprador')
            ->where('estado', 'aprobado')
            ->first();

        if (!$registroEvento) {
            $solicitudPendiente = DB::table('evento_usuario')
                ->where('evento_id', $evento->id)
                ->where('user_id', $request->user()->id)
                ->where('tipo', 'comprador')
                ->where('estado', 'pendiente')
                ->exists();

            if ($solicitudPendiente) {
                return back()->withErrors([
                    'error' => 'Tu solicitud de registro al evento está pendiente de aprobación. Recibirás una notificación cuando sea aprobada.',
                ]);
            }

            return back()->withErrors([
                'error' => 'Debes estar registrado y aprobado en el evento para agendar citas.',
            ]);
        }

        // Validar ventana temporal de compradores
        if ($evento->fecha_hora_inicio_compradores && now()->lt($evento->fecha_hora_inicio_compradores)) {
            return back()->withErrors(['fecha' => 'El período de agendado aún no ha comenzado. Apertura: ' . $evento->fecha_hora_inicio_compradores->format('d/m/Y H:i') . '.']);
        }
        if ($evento->fecha_hora_fin && now()->gt($evento->fecha_hora_fin)) {
            return back()->withErrors(['fecha' => 'El período de agendado ya cerró (fin: ' . $evento->fecha_hora_fin->format('d/m/Y H:i') . ').']);
        }

        // Validar que la fecha esté dentro del rango del evento
        $fechaCita = \Carbon\Carbon::parse($request->fecha);
        if ($evento->fecha_hora_fin && $fechaCita->gt($evento->fecha_hora_fin)) {
            return back()->withErrors(['fecha' => 'La fecha excede el período del evento (hasta ' . $evento->fecha_hora_fin->format('d/m/Y') . ').']);
        }

        $maxCitas = $evento->max_citas_por_comprador ?? 3;
        $totalCitas = $request->user()->citasComoCliente()
            ->where('edicion_id', $evento->id)
            ->whereNotIn('estado', ['cancelada'])
            ->count();
        if ($totalCitas >= $maxCitas) {
            return back()->withErrors(['limit' => "Has alcanzado el límite de {$maxCitas} citas en este evento."]);
        }

        $servicio = Servicio::where('restaurantero_id', $request->restaurantero_id)
            ->where('activo', true)
            ->first();

        if (!$servicio) {
            return back()->withErrors(['servicio' => 'Este restaurantero no tiene servicios disponibles.']);
        }

        $inicio   = \Carbon\Carbon::parse($request->fecha . ' ' . $request->hora);
        $duracion = $evento->tiempo_entre_citas_minutos ?? $servicio->duracion_minutos ?? 30;
        $fin      = $inicio->copy()->addMinutes($duracion);

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
            $cita = DB::transaction(function () use ($request, $evento, $servicio, $inicio, $fin) {
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
                    'edicion_id'        => $evento->id,
                    'restaurantero_id'  => $request->restaurantero_id,
                    'servicio_id'       => $servicio->id,
                    'cliente_id'        => $request->user()->id,
                    'inicio'            => $inicio,
                    'fin'               => $fin,
                    'estado'            => 'pendiente',
                    'notas'             => $request->notas,
                    'productos_interes' => $request->productos_interes ?: null,
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
        $evento = Evento::activo();

        $citas = collect();
        $citasCount = 0;

        if ($evento) {
            $citas = $request->user()
                ->citasComoCliente()
                ->with(['restaurantero', 'servicio'])
                ->where('edicion_id', $evento->id)
                ->orderByDesc('inicio')
                ->get();

            $citasCount = $citas->whereNotIn('estado', ['cancelada'])->count();
        }

        // Eventos pasados que tienen citas del usuario
        $edicionesHistorial = Evento::where('activa', false)
            ->orderByDesc('created_at')
            ->get()
            ->map(function ($ev) use ($request) {
                $ev->mis_citas = $request->user()
                    ->citasComoCliente()
                    ->with(['restaurantero', 'servicio'])
                    ->where('edicion_id', $ev->id)
                    ->orderByDesc('inicio')
                    ->get();
                return $ev;
            })
            ->filter(fn($ev) => $ev->mis_citas->isNotEmpty())
            ->values();

        $necesidades = $request->user()->necesidades ?? '';
        $keywords = collect(preg_split('/[\s,;\.]+/', mb_strtolower($necesidades)))
            ->filter(fn($p) => mb_strlen($p) >= 3)
            ->unique()
            ->take(15)
            ->values();

        // Solo mostrar proveedores si el comprador está aprobado en el evento activo
        $compradorAprobado = $evento && DB::table('evento_usuario')
            ->where('evento_id', $evento->id)
            ->where('user_id', $request->user()->id)
            ->where('tipo', 'comprador')
            ->where('estado', 'aprobado')
            ->exists();

        $proveedores = collect();

        if ($compradorAprobado) {
            $proveedores = Restaurantero::where('activo', true)
                ->where('aprobado', true)
                ->whereHas('user', function ($q) use ($evento) {
                    $q->whereExists(function ($sub) use ($evento) {
                        $sub->from('evento_usuario')
                            ->whereColumn('evento_usuario.user_id', 'users.id')
                            ->where('evento_usuario.evento_id', $evento->id)
                            ->where('evento_usuario.tipo', 'proveedor')
                            ->where('evento_usuario.estado', 'aprobado');
                    });
                })
                ->withCount(['citas as citas_evento_count' => fn($q) =>
                    $q->where('edicion_id', $evento->id)->whereNotIn('estado', ['cancelada', 'rechazada'])
                ])
                ->select(['id', 'nombre_restaurante', 'categoria', 'descripcion', 'logo_path', 'municipio', 'productos_top'])
                ->get()
                ->sortByDesc(function ($p) use ($keywords) {
                    if ($keywords->isEmpty()) return 0;
                    $text = mb_strtolower(
                        ($p->nombre_restaurante ?? '') . ' ' .
                        ($p->descripcion ?? '') . ' ' .
                        ($p->categoria ?? '') . ' ' .
                        implode(' ', array_column($p->productos_top ?? [], 'nombre')) . ' ' .
                        implode(' ', is_array($p->productos_top) ? array_filter($p->productos_top, 'is_string') : [])
                    );
                    return $keywords->filter(fn($kw) => str_contains($text, $kw))->count();
                })
                ->take(20)
                ->values();
        }

        $notificaciones = \App\Models\Notificacion::where('user_id', $request->user()->id)
            ->where('leida', false)
            ->orderByDesc('created_at')
            ->take(3)
            ->get(['id', 'tipo', 'titulo', 'mensaje', 'cita_id', 'created_at']);

        $todosLosEventos = Evento::orderByDesc('fecha_hora_inicio')->get()
            ->map(function ($ev) use ($request) {
                $registro = DB::table('evento_usuario')
                    ->where('evento_id', $ev->id)
                    ->where('user_id', $request->user()->id)
                    ->get();
                return [
                    'id'                            => $ev->id,
                    'nombre'                        => $ev->nombre,
                    'sector_economico'              => $ev->sector_economico,
                    'activa'                        => $ev->activa,
                    'fecha_hora_inicio'             => $ev->fecha_hora_inicio?->toISOString(),
                    'fecha_hora_fin'                => $ev->fecha_hora_fin?->toISOString(),
                    'fecha_hora_inicio_proveedores' => $ev->fecha_hora_inicio_proveedores?->toISOString(),
                    'fecha_hora_fin_proveedores'    => $ev->fecha_hora_fin_proveedores?->toISOString(),
                    'fecha_hora_inicio_compradores' => $ev->fecha_hora_inicio_compradores?->toISOString(),
                    'fecha_hora_fin_compradores'    => $ev->fecha_hora_fin_compradores?->toISOString(),
                    'max_citas_por_comprador'       => $ev->max_citas_por_comprador,
                    'tiempo_entre_citas_minutos'    => $ev->tiempo_entre_citas_minutos,
                    'registro_proveedor_abierto'    => $ev->registroProveedoresAbierto(),
                    'registro_comprador_abierto'    => $ev->registroCompradoresAbierto(),
                    'segundos_hasta_proveedores'    => $ev->segundosHastaProveedores(),
                    'segundos_hasta_compradores'    => $ev->segundosHastaCompradores(),
                    'mi_registro' => [
                        'comprador' => $registro->firstWhere('tipo', 'comprador'),
                        'proveedor' => $registro->firstWhere('tipo', 'proveedor'),
                    ],
                ];
            });

        return Inertia::render('User/Dashboard', [
            'citas'              => $citas,
            'citasCount'         => $citasCount,
            'evento'             => $evento,
            'edicionesHistorial' => $edicionesHistorial,
            'proveedores'        => $proveedores,
            'compradorAprobado'  => $compradorAprobado,
            'tieneKeywords'      => $keywords->isNotEmpty(),
            'notificaciones'     => $notificaciones,
            'eventos'            => $todosLosEventos,
        ]);
    }

    public function actualizarNotas(Request $request, Cita $cita)
    {
        if ($cita->cliente_id !== auth()->id()) {
            abort(403);
        }

        $request->validate(['notas' => 'nullable|string|max:1000']);
        $cita->update(['notas' => $request->notas]);

        return back()->with('success', 'Notas actualizadas.');
    }
}
