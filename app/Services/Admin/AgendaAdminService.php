<?php

namespace App\Services\Admin;

use App\Exceptions\ReglaDeNegocioException;
use App\Mail\PlantillaCorreoMail;
use App\Models\AgendaPropuesta;
use App\Models\AgendaPropuestaCita;
use App\Models\Cita;
use App\Models\Evento;
use App\Models\Notificacion;
use App\Models\PlantillaCorreo;
use App\Models\Restaurantero;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Agenda propuesta por el admin (flujo invertido): el admin elige un
 * COMPRADOR, le asigna proveedores en slots de 10 minutos y le envia la
 * propuesta por correo; el comprador la acepta o rechaza
 * (AgendaPropuestaService). Lo usan Admin\AgendaController (web) y la API v1.
 */
class AgendaAdminService
{
    public const MINUTOS_SLOT = 10;

    public const REGLAS_ALTA = [
        'user_id'                    => 'required|exists:users,id',
        'citas'                      => 'required|array|min:1',
        'citas.*.restaurantero_id'   => 'required|exists:restauranteros,id',
        'citas.*.slot_inicio'        => 'required|date',
        'citas.*.slot_fin'           => 'required|date|after:citas.*.slot_inicio',
        'enviar'                     => 'boolean',
    ];

    public function listar(int $porPagina = 20, array $filtros = []): LengthAwarePaginator
    {
        return AgendaPropuesta::with([
            'comprador:id,name,nombre_empresa,email',
            'citas.proveedor:id,nombre_restaurante,logo_path',
            'evento:id,nombre',
        ])
            // Filtros solo de la API (la web no los manda).
            ->when($filtros['evento_id'] ?? null, fn ($q, $id) => $q->where('evento_id', $id))
            ->when($filtros['estado'] ?? null, fn ($q, $e) => $q->where('estado', $e))
            ->latest()
            ->paginate($porPagina);
    }

    /** COMPRADORES aprobados en el evento. */
    public function compradores(Evento $evento): Collection
    {
        return User::whereExists(function ($sub) use ($evento) {
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
    }

    /** PROVEEDORES aprobados en el evento. */
    public function proveedores(Evento $evento): Collection
    {
        return Restaurantero::whereHas('user', function ($q) use ($evento) {
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
    }

    /** Slots de 10 min de 09:00 a 17:00 del dia del evento (hoy si no tiene fecha). */
    public function slots(Evento $evento): array
    {
        $fechaEvento = $evento->fecha_hora_inicio
            ? $evento->fecha_hora_inicio->format('Y-m-d')
            : now()->format('Y-m-d');

        $slots = [];
        $inicio = Carbon::parse($fechaEvento . ' 09:00:00');
        $fin    = Carbon::parse($fechaEvento . ' 17:00:00');
        while ($inicio->lt($fin)) {
            $slots[] = [
                'inicio' => $inicio->toIso8601String(),
                'fin'    => $inicio->copy()->addMinutes(self::MINUTOS_SLOT)->toIso8601String(),
                'label'  => $inicio->format('H:i'),
            ];
            $inicio->addMinutes(self::MINUTOS_SLOT);
        }

        return $slots;
    }

    /** Guarda la propuesta (y la envia si $enviar). Reglas de la web: 422 en 'error'. */
    public function crear(?Evento $evento, array $datos, User $admin): AgendaPropuesta
    {
        if (!$evento) {
            throw new ReglaDeNegocioException('No hay un evento activo.');
        }

        $existente = AgendaPropuesta::where('evento_id', $evento->id)
            ->where('user_id', $datos['user_id'])
            ->where('estado', 'pendiente')
            ->first();

        if ($existente) {
            throw new ReglaDeNegocioException('Ya existe una propuesta pendiente para este comprador.');
        }

        $restauranteroIds = array_column($datos['citas'], 'restaurantero_id');
        if (count($restauranteroIds) !== count(array_unique($restauranteroIds))) {
            throw new ReglaDeNegocioException('No puedes asignar el mismo proveedor dos veces en la misma propuesta.');
        }

        foreach ($datos['citas'] as $cita) {
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
                throw new ReglaDeNegocioException('El proveedor ' . ($proveedor->nombre_restaurante ?? '') .
                    ' ya tiene una cita confirmada en ese horario.');
            }
        }

        return DB::transaction(function () use ($datos, $evento, $admin) {
            $propuesta = AgendaPropuesta::create([
                'evento_id' => $evento->id,
                'user_id'   => $datos['user_id'],
                'admin_id'  => $admin->id,
                'estado'    => 'pendiente',
            ]);

            foreach ($datos['citas'] as $cita) {
                AgendaPropuestaCita::create([
                    'agenda_propuesta_id' => $propuesta->id,
                    'restaurantero_id'    => $cita['restaurantero_id'],
                    'slot_inicio'         => $cita['slot_inicio'],
                    'slot_fin'            => $cita['slot_fin'],
                ]);
            }

            if (!empty($datos['enviar'])) {
                $this->enviarCorreo($propuesta);
            }

            return $propuesta;
        });
    }

    /** Si fue aceptada, tambien las citas que genero para el comprador en ese evento. */
    public function citasConfirmadas(AgendaPropuesta $agenda): Collection
    {
        if ($agenda->estado !== 'aceptada') {
            return new \Illuminate\Database\Eloquent\Collection();
        }

        return Cita::where('cliente_id', $agenda->user_id)
            ->where('edicion_id', $agenda->evento_id)
            ->with('restaurantero:id,nombre_restaurante,logo_path')
            ->orderBy('inicio')
            ->get();
    }

    /** (Re)envia el correo de una propuesta PENDIENTE. */
    public function enviar(AgendaPropuesta $agenda): AgendaPropuesta
    {
        if ($agenda->estado !== 'pendiente') {
            throw new ReglaDeNegocioException('Solo se pueden enviar propuestas pendientes.');
        }

        $this->enviarCorreo($agenda);

        return $agenda;
    }

    /**
     * Borra la propuesta. Si estaba aceptada, CANCELA (no borra) solo las citas
     * que salieron de ella y avisa al proveedor. Devuelve el mensaje de la web.
     */
    public function eliminar(AgendaPropuesta $agenda): string
    {
        $canceladas = 0;

        // Se guarda ANTES de la transaccion: despues del delete() el modelo ya
        // no conserva el estado y el mensaje de abajo saldria vacio.
        $estadoOriginal = $agenda->estado;

        DB::transaction(function () use ($agenda, &$canceladas) {
            if ($agenda->estado === 'aceptada') {
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

        return $canceladas > 0
            ? "Propuesta eliminada. Se cancelaron {$canceladas} cita(s) generadas por ella."
            : ($estadoOriginal === 'aceptada'
                ? 'Propuesta eliminada, pero NO se canceló ninguna cita: esta agenda es anterior al registro de vínculos. Revisa las citas del comprador a mano si hace falta.'
                : 'Propuesta de agenda eliminada correctamente.');
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
                Log::warning('Error enviando correo de propuesta de agenda: ' . $e->getMessage());
            }
        }

        $propuesta->update(['enviada_at' => now()]);
    }
}
