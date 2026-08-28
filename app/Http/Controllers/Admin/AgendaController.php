<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\PlantillaCorreoMail;
use App\Models\AgendaPropuesta;
use App\Models\AgendaPropuestaCita;
use App\Models\Cita;
use App\Models\Evento;
use App\Models\Notificacion;
use App\Models\PlantillaCorreo;
use App\Models\Restaurantero;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;

class AgendaController extends Controller
{
    public function index()
    {
        $propuestas = AgendaPropuesta::with([
            'comprador:id,name,nombre_empresa,email',
            'citas.proveedor:id,nombre_restaurante,logo_path',
            'evento:id,nombre',
        ])
            ->latest()
            ->paginate(20);

        return Inertia::render('Admin/Agenda/Index', [
            'propuestas' => $propuestas,
        ]);
    }

    public function crear()
    {
        $evento = Evento::contextoAdmin();

        if (!$evento) {
            return redirect()->route('admin.agenda.index')
                ->with('error', 'No hay un evento activo para crear agendas.');
        }

        // COMPRADORES aprobados en el evento activo
        $compradores = User::whereExists(function ($sub) use ($evento) {
                $sub->from('evento_usuario')
                    ->whereColumn('evento_usuario.user_id', 'users.id')
                    ->where('evento_usuario.evento_id', $evento->id)
                    ->where('evento_usuario.tipo', 'comprador')
                    ->where('evento_usuario.estado', 'aprobado');
            })
            ->select('id', 'name', 'nombre_empresa', 'municipio', 'camara_asociacion')
            ->orderBy('nombre_empresa')
            ->orderBy('name')
            ->get();

        // PROVEEDORES aprobados en el evento activo
        $proveedores = Restaurantero::whereHas('user', function ($q) use ($evento) {
                $q->whereExists(function ($sub) use ($evento) {
                    $sub->from('evento_usuario')
                        ->whereColumn('evento_usuario.user_id', 'users.id')
                        ->where('evento_usuario.evento_id', $evento->id)
                        ->where('evento_usuario.tipo', 'proveedor')
                        ->where('evento_usuario.estado', 'aprobado');
                });
            })
            ->select('id', 'nombre_restaurante', 'logo_path', 'descripcion', 'categoria')
            ->orderBy('nombre_restaurante')
            ->get();

        $fechaEvento = $evento->fecha_hora_inicio
            ? $evento->fecha_hora_inicio->format('Y-m-d')
            : now()->format('Y-m-d');

        $slots = [];
        $inicio = \Carbon\Carbon::parse($fechaEvento . ' 09:00:00');
        $fin    = \Carbon\Carbon::parse($fechaEvento . ' 17:00:00');
        while ($inicio->lt($fin)) {
            $slots[] = [
                'inicio' => $inicio->toIso8601String(),
                'fin'    => $inicio->copy()->addMinutes(10)->toIso8601String(),
                'label'  => $inicio->format('H:i'),
            ];
            $inicio->addMinutes(10);
        }

        return Inertia::render('Admin/Agenda/Crear', [
            'evento'      => $evento->only(['id', 'nombre']),
            'compradores' => $compradores,
            'proveedores' => $proveedores,
            'slots'       => $slots,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'user_id'                    => 'required|exists:users,id',
            'citas'                      => 'required|array|min:1',
            'citas.*.restaurantero_id'   => 'required|exists:restauranteros,id',
            'citas.*.slot_inicio'        => 'required|date',
            'citas.*.slot_fin'           => 'required|date|after:citas.*.slot_inicio',
            'enviar'                     => 'boolean',
        ]);

        $evento = Evento::contextoAdmin();
        if (!$evento) {
            return back()->withErrors(['error' => 'No hay un evento activo.']);
        }

        $existente = AgendaPropuesta::where('evento_id', $evento->id)
            ->where('user_id', $request->user_id)
            ->where('estado', 'pendiente')
            ->first();

        if ($existente) {
            return back()->withErrors(['error' => 'Ya existe una propuesta pendiente para este comprador.']);
        }

        $restauranteroIds = array_column($request->citas, 'restaurantero_id');
        if (count($restauranteroIds) !== count(array_unique($restauranteroIds))) {
            return back()->withErrors(['error' => 'No puedes asignar el mismo proveedor dos veces en la misma propuesta.']);
        }

        foreach ($request->citas as $cita) {
            $conflicto = AgendaPropuestaCita::where('restaurantero_id', $cita['restaurantero_id'])
                ->whereHas('agenda', function ($q) use ($evento) {
                    $q->where('evento_id', $evento->id)->where('estado', 'aceptada');
                })
                ->where(function ($q) use ($cita) {
                    $q->whereBetween('slot_inicio', [$cita['slot_inicio'], $cita['slot_fin']])
                      ->orWhereBetween('slot_fin', [$cita['slot_inicio'], $cita['slot_fin']]);
                })
                ->exists();

            if ($conflicto) {
                $proveedor = Restaurantero::find($cita['restaurantero_id']);
                return back()->withErrors([
                    'error' => 'El proveedor ' . ($proveedor->nombre_restaurante ?? '') .
                               ' ya tiene una cita confirmada en ese horario.',
                ]);
            }
        }

        DB::transaction(function () use ($request, $evento) {
            $propuesta = AgendaPropuesta::create([
                'evento_id' => $evento->id,
                'user_id'   => $request->user_id,
                'admin_id'  => auth()->id(),
                'estado'    => 'pendiente',
            ]);

            foreach ($request->citas as $cita) {
                AgendaPropuestaCita::create([
                    'agenda_propuesta_id' => $propuesta->id,
                    'restaurantero_id'    => $cita['restaurantero_id'],
                    'slot_inicio'         => $cita['slot_inicio'],
                    'slot_fin'            => $cita['slot_fin'],
                ]);
            }

            if ($request->boolean('enviar')) {
                $this->enviarCorreo($propuesta);
            }
        });

        return redirect()->route('admin.agenda.index')
            ->with('success', 'Propuesta de agenda guardada' .
                ($request->boolean('enviar') ? ' y enviada al comprador.' : '.'));
    }

    public function show(AgendaPropuesta $agenda)
    {
        $agenda->load([
            'comprador:id,name,nombre_empresa,email',
            'evento:id,nombre,fecha_hora_inicio',
            'citas.proveedor:id,nombre_restaurante,logo_path',
        ]);

        $citasConfirmadas = [];
        if ($agenda->estado === 'aceptada') {
            $citasConfirmadas = Cita::where('cliente_id', $agenda->user_id)
                ->where('edicion_id', $agenda->evento_id)
                ->with('restaurantero:id,nombre_restaurante,logo_path')
                ->orderBy('inicio')
                ->get();
        }

        return Inertia::render('Admin/Agenda/Show', [
            'propuesta'        => $agenda,
            'citasConfirmadas' => $citasConfirmadas,
        ]);
    }

    public function enviar(AgendaPropuesta $agenda)
    {
        if ($agenda->estado !== 'pendiente') {
            return back()->withErrors(['error' => 'Solo se pueden enviar propuestas pendientes.']);
        }

        $this->enviarCorreo($agenda);

        return back()->with('success', 'Correo enviado al comprador.');
    }

    public function destroy(AgendaPropuesta $agenda)
    {
        $canceladas = 0;

        // Se guarda ANTES de la transaccion: despues del delete() el modelo ya
        // no conserva el estado y el mensaje de abajo saldria vacio.
        $estadoOriginal = $agenda->estado;

        DB::transaction(function () use ($agenda, &$canceladas) {
            if ($agenda->estado === 'aceptada') {
                // SOLO las citas que salieron de esta propuesta, y se CANCELAN
                // (no se borran) para conservar el historico y poder notificar.
                $citaIds = $agenda->citas()->whereNotNull('cita_id')->pluck('cita_id');

                $citas = Cita::whereIn('id', $citaIds)
                    ->whereNotIn('estado', ['cancelada', 'completada'])
                    ->with('restaurantero')
                    ->get();

                foreach ($citas as $cita) {
                    $cita->update(['estado' => 'cancelada']);
                    $canceladas++;

                    if ($cita->restaurantero?->user_id) {
                        Notificacion::crear(
                            $cita->restaurantero->user_id,
                            'cita_cancelada',
                            'Cita cancelada',
                            'Se cancelo tu cita del ' . $cita->inicio->format('d/m/Y H:i') .
                            ' porque el administrador elimino la propuesta de agenda asociada.'
                        );
                    }
                }
            }

            $agenda->citas()->delete();
            $agenda->delete();
        });

        $mensaje = $canceladas > 0
            ? "Propuesta eliminada. Se cancelaron {$canceladas} cita(s) generadas por ella."
            : ($estadoOriginal === 'aceptada'
                ? 'Propuesta eliminada, pero NO se canceló ninguna cita: esta agenda es anterior al registro de vínculos. Revisa las citas del comprador a mano si hace falta.'
                : 'Propuesta de agenda eliminada correctamente.');

        return redirect()->route('admin.agenda.index')->with('success', $mensaje);
    }

    private function enviarCorreo(AgendaPropuesta $propuesta): void
    {
        $propuesta->load(['comprador', 'citas.proveedor:id,nombre_restaurante', 'evento']);

        $token = $propuesta->generarToken();

        $urlAceptar  = route('agenda.aceptar', $token);
        $urlRechazar = route('agenda.rechazar', $token);

        $listaCitas = $propuesta->citas->sortBy('slot_inicio')->map(function ($c) {
            $hora = $c->slot_inicio->format('H:i') . ' – ' . $c->slot_fin->format('H:i');
            return '<strong>' . $hora . '</strong> → ' . e($c->proveedor->nombre_restaurante ?? 'Proveedor');
        })->implode('<br>');

        $nombreComprador = $propuesta->comprador->nombre_empresa ?? $propuesta->comprador->name;

        $plantilla = PlantillaCorreo::paraClave('agenda_propuesta');

        if ($plantilla && $propuesta->comprador->email) {
            $mail = new PlantillaCorreoMail($plantilla, [
                'nombre_comprador' => $nombreComprador,
                'nombre_evento'    => $propuesta->evento->nombre,
                'fecha_evento'     => $propuesta->evento->fecha_hora_inicio?->translatedFormat('d \d\e F \d\e Y') ?? '',
                'lista_citas'      => $listaCitas,
                'url_aceptar'      => $urlAceptar,
                'url_rechazar'     => $urlRechazar,
            ]);

            try {
                Mail::to($propuesta->comprador->email)->send($mail);
            } catch (\Exception $e) {
                \Log::warning('Error enviando correo de propuesta de agenda: ' . $e->getMessage());
            }
        }

        $propuesta->update(['enviada_at' => now()]);
    }
}
