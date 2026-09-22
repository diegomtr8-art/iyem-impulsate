<?php

namespace App\Services;

use App\Exceptions\ReglaDeNegocioException;
use App\Mail\CitaAgendada;
use App\Models\Cita;
use App\Models\Evento;
use App\Models\Notificacion;
use App\Models\Restaurantero;
use App\Models\Servicio;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Alta y reagenda de citas. Antes esta logica estaba copiada en
 * CitaPublicaController, CitaAdminController y RestauranteroCitasController,
 * cada una con su propia formula de duracion; ahora todas pasan por aqui.
 *
 * Reglas unicas:
 *  - Duracion: la pedida explicitamente (solo admin) ?? la del evento ?? la del servicio ?? 30.
 *  - Cupo por comprador: no cuentan las citas canceladas ni rechazadas.
 *  - Colchon de 10 min SOLO para el comprador. Al proveedor no se le aplica:
 *    sus horarios se ofrecen pegados (paso = tiempo_entre_citas_minutos en
 *    Restauranteros/Show.vue) y con colchon cada horario contiguo a una cita
 *    quedaria rechazado aunque la pantalla lo muestre libre.
 *  - Todo dentro de DB::transaction con lockForUpdate sobre comprador y proveedor.
 */
class AgendaCitaService
{
    public const COLCHON_COMPRADOR_MINUTOS = 10;
    public const DURACION_POR_DEFECTO = 30;

    /** Estados que liberan el horario y no cuentan para el cupo. */
    public const ESTADOS_INACTIVOS = ['cancelada', 'rechazada'];

    /**
     * El encuentro de negocios activo en el que el comprador esta APROBADO.
     * Con varios eventos activos no se puede asumir "el mas proximo".
     */
    public function eventoDelComprador(User $comprador): Evento
    {
        $eventosActivos = Evento::queryActivos()
            ->where('tipo_evento', 'encuentro_negocios')
            ->pluck('id');

        if ($eventosActivos->isEmpty()) {
            throw new ReglaDeNegocioException('No hay un encuentro de negocios activo. Contacta al administrador.', 'edicion');
        }

        $registros = DB::table('evento_usuario')
            ->whereIn('evento_id', $eventosActivos)
            ->where('user_id', $comprador->id)
            ->where('tipo', 'comprador');

        $aprobado = (clone $registros)->where('estado', 'aprobado')->first();

        if (!$aprobado) {
            if ((clone $registros)->where('estado', 'pendiente')->exists()) {
                throw new ReglaDeNegocioException('Tu solicitud de registro al evento está pendiente de aprobación. Recibirás una notificación cuando sea aprobada.');
            }
            throw new ReglaDeNegocioException('Debes estar registrado y aprobado en el evento para agendar citas.');
        }

        $evento = Evento::find($aprobado->evento_id);
        if (!$evento) {
            throw new ReglaDeNegocioException('No se encontro el evento de tu inscripcion. Contacta al administrador.', 'edicion');
        }

        return $evento;
    }

    /** Ventana de agendado del comprador y rango de fechas del evento presencial. */
    public function validarVentanaComprador(Evento $evento, Carbon $fechaCita): void
    {
        if ($evento->fecha_hora_inicio_compradores && now()->lt($evento->fecha_hora_inicio_compradores)) {
            throw new ReglaDeNegocioException('El período de agendado aún no ha comenzado. Apertura: ' . $evento->fecha_hora_inicio_compradores->format('d/m/Y H:i') . '.', 'fecha');
        }
        if ($evento->fecha_hora_fin && now()->gt($evento->fecha_hora_fin)) {
            throw new ReglaDeNegocioException('El período de agendado ya cerró (fin: ' . $evento->fecha_hora_fin->format('d/m/Y H:i') . ').', 'fecha');
        }
        if ($evento->fecha_hora_inicio && $fechaCita->lt($evento->fecha_hora_inicio->copy()->startOfDay())) {
            throw new ReglaDeNegocioException('Solo puedes agendar citas para el día del evento (' . $evento->fecha_hora_inicio->format('d/m/Y') . ').', 'fecha');
        }
        if ($evento->fecha_hora_fin && $fechaCita->gt($evento->fecha_hora_fin)) {
            throw new ReglaDeNegocioException('La fecha excede el período del evento (hasta ' . $evento->fecha_hora_fin->format('d/m/Y') . ').', 'fecha');
        }
    }

    public function duracion(?Evento $evento, ?Servicio $servicio, ?int $solicitada = null): int
    {
        return (int) ($solicitada
            ?? $evento?->tiempo_entre_citas_minutos
            ?? $servicio?->duracion_minutos
            ?? self::DURACION_POR_DEFECTO);
    }

    /**
     * Crea la cita y avisa a las partes.
     *
     * @param array{estado?: string, notas?: ?string, productos_interes?: ?array, origen?: 'comprador'|'admin'} $opciones
     */
    public function agendar(
        User $comprador,
        int $restauranteroId,
        Evento $evento,
        Carbon $inicio,
        ?int $duracion = null,
        array $opciones = [],
    ): Cita {
        $origen = $opciones['origen'] ?? 'comprador';
        $esAdmin = $origen === 'admin';

        $servicio = Servicio::where('restaurantero_id', $restauranteroId)
            ->where('activo', true)
            ->first();

        if (!$servicio) {
            throw new ReglaDeNegocioException('Este proveedor no tiene servicios disponibles.', 'servicio');
        }

        $fin = $inicio->copy()->addMinutes($this->duracion($evento, $servicio, $duracion));

        $cita = DB::transaction(function () use ($comprador, $restauranteroId, $evento, $servicio, $inicio, $fin, $opciones, $esAdmin) {
            // Siempre en este orden (comprador, luego proveedor) para no crear
            // interbloqueos entre dos altas simultaneas.
            User::whereKey($comprador->id)->lockForUpdate()->first();
            Restaurantero::whereKey($restauranteroId)->lockForUpdate()->first();

            $maxCitas = $evento->max_citas_por_comprador ?? 3;
            $totalCitas = $comprador->citasComoCliente()
                ->where('edicion_id', $evento->id)
                ->whereNotIn('estado', self::ESTADOS_INACTIVOS)
                ->count();
            if ($totalCitas >= $maxCitas) {
                throw new ReglaDeNegocioException($esAdmin
                    ? "Este cliente ya alcanzó el límite de {$maxCitas} citas en este evento."
                    : "Has alcanzado el límite de {$maxCitas} citas en este evento.", 'limit');
            }

            $this->verificarDisponibilidad($comprador->id, $restauranteroId, $inicio, $fin, null, $esAdmin);

            return Cita::create([
                'edicion_id'        => $evento->id,
                'restaurantero_id'  => $restauranteroId,
                'servicio_id'       => $servicio->id,
                'cliente_id'        => $comprador->id,
                'inicio'            => $inicio,
                'fin'               => $fin,
                'estado'            => $opciones['estado'] ?? 'pendiente',
                'notas'             => $opciones['notas'] ?? null,
                'productos_interes' => ($opciones['productos_interes'] ?? null) ?: null,
            ]);
        });

        $cita->load(['restaurantero.user', 'cliente']);
        $this->avisarAlta($cita, $esAdmin);

        return $cita;
    }

    /**
     * Lanza ReglaDeNegocioException si el horario choca con otra cita del
     * comprador (con colchon) o del proveedor (sin colchon). $excluirCitaId
     * permite comprobar una reagenda sin que la cita choque consigo misma.
     * Llamar dentro de una transaccion con los bloqueos ya tomados.
     */
    public function verificarDisponibilidad(
        int $compradorId,
        int $restauranteroId,
        Carbon $inicio,
        Carbon $fin,
        ?int $excluirCitaId = null,
        bool $esAdmin = false,
    ): void {
        $colchon = self::COLCHON_COMPRADOR_MINUTOS;

        $conflictoComprador = Cita::where('cliente_id', $compradorId)
            ->whereNotIn('estado', self::ESTADOS_INACTIVOS)
            ->when($excluirCitaId, fn ($q) => $q->whereKeyNot($excluirCitaId))
            ->where('inicio', '<', $fin->copy()->addMinutes($colchon))
            ->where('fin', '>', $inicio->copy()->subMinutes($colchon))
            ->first();

        if ($conflictoComprador) {
            $hora = Carbon::parse($conflictoComprador->inicio)->format('H:i');
            throw new ReglaDeNegocioException(($esAdmin
                ? "Este cliente ya tiene una cita a las {$hora}"
                : "Ya tienes una cita agendada a las {$hora}") . " (se requieren {$colchon} minutos de separación entre citas).", 'fecha');
        }

        $conflictoProveedor = Cita::where('restaurantero_id', $restauranteroId)
            ->whereNotIn('estado', self::ESTADOS_INACTIVOS)
            ->when($excluirCitaId, fn ($q) => $q->whereKeyNot($excluirCitaId))
            ->where('inicio', '<', $fin)
            ->where('fin', '>', $inicio)
            ->exists();

        if ($conflictoProveedor) {
            throw new ReglaDeNegocioException($esAdmin
                ? 'El proveedor ya tiene una cita en ese horario. Elige otro.'
                : 'Este horario ya no está disponible con el proveedor. Por favor elige otro.', 'fecha');
        }
    }

    /** Horario propuesto por el proveedor al reagendar, con la misma regla de duracion. */
    public function proponerReagenda(Cita $cita, Carbon $propuestaInicio): Carbon
    {
        $cita->loadMissing(['servicio', 'evento']);
        $propuestaFin = $propuestaInicio->copy()->addMinutes($this->duracion($cita->evento, $cita->servicio));

        DB::transaction(function () use ($cita, $propuestaInicio, $propuestaFin) {
            $this->verificarDisponibilidad($cita->cliente_id, $cita->restaurantero_id, $propuestaInicio, $propuestaFin, $cita->id);
        });

        return $propuestaFin;
    }

    /**
     * El comprador acepta la reagenda. Antes se reescribia el horario sin
     * transaccion ni comprobar solapes: si entretanto alguien habia tomado ese
     * hueco, quedaban dos citas encima.
     */
    public function confirmarReagenda(Cita $cita): Cita
    {
        DB::transaction(function () use ($cita) {
            User::whereKey($cita->cliente_id)->lockForUpdate()->first();
            Restaurantero::whereKey($cita->restaurantero_id)->lockForUpdate()->first();

            $cita->refresh();
            if ($cita->estado !== 'pendiente_reconfirmacion' || !$cita->propuesta_inicio) {
                throw new ReglaDeNegocioException('El enlace no es válido o ya fue utilizado.');
            }

            $this->verificarDisponibilidad(
                $cita->cliente_id,
                $cita->restaurantero_id,
                Carbon::parse($cita->propuesta_inicio),
                Carbon::parse($cita->propuesta_fin),
                $cita->id,
            );

            $cita->update([
                'estado'             => 'confirmada',
                'inicio'             => $cita->propuesta_inicio,
                'fin'                => $cita->propuesta_fin,
                'token_confirmacion' => null,
            ]);
        });

        return $cita;
    }

    private function avisarAlta(Cita $cita, bool $esAdmin): void
    {
        try {
            Mail::to($cita->cliente->email)->send(new CitaAgendada($cita, 'cliente'));
            if ($emailProveedor = $cita->restaurantero?->user?->email) {
                Mail::to($emailProveedor)->send(new CitaAgendada($cita, 'proveedor'));
            }
        } catch (\Throwable $e) {
            Log::warning('Error enviando correo de cita agendada: ' . $e->getMessage());
        }

        $fechaFmt = $cita->inicio->format('d/m/Y H:i');
        $proveedorUserId = $cita->restaurantero?->user_id;

        if ($esAdmin) {
            if ($proveedorUserId) {
                Notificacion::crear($proveedorUserId, 'cita_nueva', 'Nueva cita asignada',
                    "El administrador te asigno una cita el {$fechaFmt}.", $cita->id);
            }
            return;
        }

        Notificacion::crear($cita->cliente_id, 'cita_nueva', 'Cita agendada',
            "Tu cita con {$cita->restaurantero->nombre_restaurante} el {$fechaFmt} fue registrada.", $cita->id);

        if ($proveedorUserId) {
            Notificacion::crear($proveedorUserId, 'cita_nueva', 'Nueva cita solicitada',
                "{$cita->cliente->name} agendó una cita contigo para el {$fechaFmt}.", $cita->id);
        }

        User::role('admin')->each(fn ($a) =>
            Notificacion::crear($a->id, 'cita_nueva', 'Nueva cita en el sistema',
                "{$cita->cliente->name} agendó cita con {$cita->restaurantero->nombre_restaurante} el {$fechaFmt}.", $cita->id)
        );
    }
}
