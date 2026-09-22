<?php

namespace App\Services;

use App\Exceptions\ReglaDeNegocioException;
use App\Mail\CitaAgendada;
use App\Mail\CitaCancelada;
use App\Mail\CitaReagendada;
use App\Models\Cita;
use App\Models\Evento;
use App\Models\Notificacion;
use App\Models\Restaurantero;
use App\Models\Servicio;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Builder;
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

    /** El comprador no puede cancelar una cita en estos estados (CitaPublicaController::destroy). */
    public const ESTADOS_NO_CANCELABLES = ['completada', 'cancelada'];

    public const MAX_CITAS_POR_DEFECTO = 3;

    /** Validacion del alta del comprador (web POST /citas y API POST /citas). */
    public const REGLAS_ALTA_COMPRADOR = [
        'restaurantero_id'    => 'required|exists:restauranteros,id',
        'fecha'               => 'required|date|after_or_equal:today',
        'hora'                => 'required|date_format:H:i',
        'notas'               => 'nullable|string|max:1000',
        'productos_interes'   => 'nullable|array',
        'productos_interes.*' => 'string|max:255',
    ];

    /** Estados de la casilla en disponibilidad(). */
    public const SLOT_LIBRE = 'libre';
    public const SLOT_OCUPADO_PROVEEDOR = 'ocupado_proveedor';
    public const SLOT_BLOQUEADO_COMPRADOR = 'bloqueado_por_tus_citas';
    public const SLOT_FUERA_DE_VENTANA = 'fuera_de_ventana';

    /** El administrador no agenda citas para si mismo (lo hace desde su panel). */
    public function asegurarNoEsAdmin(User $user): void
    {
        if ($user->hasRole('admin')) {
            throw new ReglaDeNegocioException('El administrador no puede agendar citas para sí mismo. Usa el panel de administración para gestionar citas de clientes.');
        }
    }

    /**
     * Alta desde el lado del comprador (web y API). $datos ya validado con
     * REGLAS_ALTA_COMPRADOR.
     *
     * @param array{restaurantero_id: int|string, fecha: string, hora: string, notas?: ?string, productos_interes?: ?array} $datos
     */
    public function agendarComoComprador(User $comprador, array $datos): Cita
    {
        $this->asegurarNoEsAdmin($comprador);

        $evento = $this->eventoDelComprador($comprador);
        $this->validarVentanaComprador($evento, Carbon::parse($datos['fecha']));

        return $this->agendar(
            $comprador,
            (int) $datos['restaurantero_id'],
            $evento,
            Carbon::parse($datos['fecha'] . ' ' . $datos['hora']),
            null,
            [
                'estado'            => 'pendiente',
                'notas'             => $datos['notas'] ?? null,
                'productos_interes' => $datos['productos_interes'] ?? null,
            ],
        );
    }

    /** Citas que cuentan para el cupo del comprador en el evento. */
    public function citasUsadas(User $comprador, Evento $evento): int
    {
        return $comprador->citasComoCliente()
            ->where('edicion_id', $evento->id)
            ->whereNotIn('estado', self::ESTADOS_INACTIVOS)
            ->count();
    }

    public function maxCitas(Evento $evento): int
    {
        return (int) ($evento->max_citas_por_comprador ?? self::MAX_CITAS_POR_DEFECTO);
    }

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

            $maxCitas = $this->maxCitas($evento);
            if ($this->citasUsadas($comprador, $evento) >= $maxCitas) {
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

        $conflictoComprador = $this->citasQueChocan('comprador', $compradorId, $inicio, $fin)
            ->when($excluirCitaId, fn ($q) => $q->whereKeyNot($excluirCitaId))
            ->first();

        if ($conflictoComprador) {
            $hora = Carbon::parse($conflictoComprador->inicio)->format('H:i');
            throw new ReglaDeNegocioException(($esAdmin
                ? "Este cliente ya tiene una cita a las {$hora}"
                : "Ya tienes una cita agendada a las {$hora}") . " (se requieren {$colchon} minutos de separación entre citas).", 'fecha');
        }

        $conflictoProveedor = $this->citasQueChocan('proveedor', $restauranteroId, $inicio, $fin)
            ->when($excluirCitaId, fn ($q) => $q->whereKeyNot($excluirCitaId))
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

    /**
     * Intervalo que una cita de $lado bloquea alrededor de [$inicio, $fin]:
     * el comprador necesita COLCHON_COMPRADOR_MINUTOS antes y despues; el
     * proveedor no (sus horarios se ofrecen pegados). Unica fuente de la regla
     * de solape para verificarDisponibilidad() y disponibilidad().
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    private function rangoBloqueado(string $lado, Carbon $inicio, Carbon $fin): array
    {
        $colchon = $lado === 'comprador' ? self::COLCHON_COMPRADOR_MINUTOS : 0;

        return [$inicio->copy()->subMinutes($colchon), $fin->copy()->addMinutes($colchon)];
    }

    /** Citas activas del comprador o del proveedor que chocan con [$inicio, $fin]. */
    private function citasQueChocan(string $lado, int $id, Carbon $inicio, Carbon $fin): Builder
    {
        [$desde, $hasta] = $this->rangoBloqueado($lado, $inicio, $fin);

        return Cita::query()
            ->where($lado === 'comprador' ? 'cliente_id' : 'restaurantero_id', $id)
            ->whereNotIn('estado', self::ESTADOS_INACTIVOS)
            ->where('inicio', '<', $hasta)
            ->where('fin', '>', $desde);
    }

    private function chocaCon($citas, string $lado, Carbon $inicio, Carbon $fin): bool
    {
        [$desde, $hasta] = $this->rangoBloqueado($lado, $inicio, $fin);

        return $citas->contains(fn (Cita $c) => $c->inicio->lt($hasta) && $c->fin->gt($desde));
    }

    /**
     * Horarios que ofrece la ficha del proveedor (misma generacion que
     * Restauranteros/Show.vue: cada dia del evento, de la hora de inicio a la de
     * fin del evento, con paso = tiempo_entre_citas_minutos redondeado a 5) con
     * el estado de cada uno segun las MISMAS reglas que aplica agendar():
     *
     *  - fuera_de_ventana: ya paso, o la ventana de agendado del comprador esta cerrada.
     *  - ocupado_proveedor: choca con una cita activa del proveedor.
     *  - bloqueado_por_tus_citas: choca con una cita activa del comprador (+/- colchon).
     *  - libre.
     *
     * puede_agendar/motivo resumen lo que agendar() diria aunque el horario
     * este libre (no aprobado, admin, sin servicio, cupo lleno...).
     */
    public function disponibilidad(User $comprador, Restaurantero $restaurantero, ?Carbon $desde = null, ?Carbon $hasta = null): array
    {
        $motivo = null;
        $evento = null;

        try {
            $this->asegurarNoEsAdmin($comprador);
            $evento = $this->eventoDelComprador($comprador);
        } catch (ReglaDeNegocioException $e) {
            $motivo = $e->getMessage();
        }

        // Sin inscripcion aprobada la ficha web muestra la rejilla del evento
        // principal; aqui igual, pero con puede_agendar=false y el motivo.
        $evento ??= Evento::activo();

        if (!$evento) {
            return [
                'evento'                => null,
                'duracion_cita_minutos' => null,
                'intervalo_minutos'     => null,
                'colchon_minutos'       => self::COLCHON_COMPRADOR_MINUTOS,
                'citas_usadas'          => 0,
                'max_citas'             => null,
                'citas_restantes'       => 0,
                'puede_agendar'         => false,
                'motivo'                => $motivo ?? 'No hay un encuentro de negocios activo. Contacta al administrador.',
                'dias'                  => [],
            ];
        }

        $servicio = Servicio::where('restaurantero_id', $restaurantero->id)->where('activo', true)->first();
        $duracion = $this->duracion($evento, $servicio);
        $paso     = max(5, (int) round(($evento->tiempo_entre_citas_minutos ?? self::DURACION_POR_DEFECTO) / 5) * 5);

        $motivoVentana = null;
        if ($evento->fecha_hora_inicio) {
            try {
                $this->validarVentanaComprador($evento, $evento->fecha_hora_inicio->copy()->startOfDay());
            } catch (ReglaDeNegocioException $e) {
                $motivoVentana = $e->getMessage();
            }
        }

        $usadas = $this->citasUsadas($comprador, $evento);
        $max    = $this->maxCitas($evento);

        $motivo ??= $motivoVentana;
        if (!$motivo && !$servicio) {
            $motivo = 'Este proveedor no tiene servicios disponibles.';
        }
        if (!$motivo && $usadas >= $max) {
            $motivo = "Has alcanzado el límite de {$max} citas en este evento.";
        }

        return [
            'evento' => [
                'id'                         => $evento->id,
                'nombre'                     => $evento->nombre,
                'fecha_hora_inicio'          => $evento->fecha_hora_inicio?->toIso8601String(),
                'fecha_hora_fin'             => $evento->fecha_hora_fin?->toIso8601String(),
                'ventana_agendado'           => [
                    'inicio'  => $evento->fecha_hora_inicio_compradores?->toIso8601String(),
                    'fin'     => $evento->fecha_hora_fin?->toIso8601String(),
                    'abierta' => $motivoVentana === null,
                ],
                'tiempo_entre_citas_minutos' => (int) ($evento->tiempo_entre_citas_minutos ?? self::DURACION_POR_DEFECTO),
                'max_citas_por_comprador'    => $max,
            ],
            'duracion_cita_minutos' => $duracion,
            'intervalo_minutos'     => $paso,
            'colchon_minutos'       => self::COLCHON_COMPRADOR_MINUTOS,
            'citas_usadas'          => $usadas,
            'max_citas'             => $max,
            'citas_restantes'       => max(0, $max - $usadas),
            'puede_agendar'         => $motivo === null,
            'motivo'                => $motivo,
            'dias'                  => $this->diasConHorarios($evento, $comprador, $restaurantero, $duracion, $paso, $motivoVentana !== null, $desde, $hasta),
        ];
    }

    private function diasConHorarios(
        Evento $evento,
        User $comprador,
        Restaurantero $restaurantero,
        int $duracion,
        int $paso,
        bool $ventanaCerrada,
        ?Carbon $desde,
        ?Carbon $hasta,
    ): array {
        if (!$evento->fecha_hora_inicio) {
            return [];
        }

        $inicioEvento = $evento->fecha_hora_inicio;
        $finEvento    = $evento->fecha_hora_fin;

        // Horas del evento (la ficha web usa 09:00-16:00 si faltan).
        $minInicio = $inicioEvento->hour * 60 + $inicioEvento->minute;
        $minFin    = $finEvento ? $finEvento->hour * 60 + $finEvento->minute : 16 * 60;

        $primerDia = $inicioEvento->copy()->startOfDay();
        $ultimoDia = ($finEvento ?? $inicioEvento)->copy()->startOfDay();
        if ($desde && $desde->copy()->startOfDay()->gt($primerDia)) {
            $primerDia = $desde->copy()->startOfDay();
        }
        if ($hasta && $hasta->copy()->startOfDay()->lt($ultimoDia)) {
            $ultimoDia = $hasta->copy()->startOfDay();
        }
        if ($primerDia->gt($ultimoDia) || $minInicio >= $minFin) {
            return [];
        }

        // Todas las citas que pueden chocar con algun horario del rango, en dos consultas.
        $rangoInicio = $primerDia->copy()->addMinutes($minInicio);
        $rangoFin    = $ultimoDia->copy()->addMinutes($minFin + $duracion);
        $delProveedor = $this->citasQueChocan('proveedor', $restaurantero->id, $rangoInicio, $rangoFin)->get(['id', 'inicio', 'fin']);
        $delComprador = $this->citasQueChocan('comprador', $comprador->id, $rangoInicio, $rangoFin)->get(['id', 'inicio', 'fin']);

        $ahora = now();
        $dias = [];
        foreach (CarbonPeriod::create($primerDia, '1 day', $ultimoDia) as $dia) {
            $horarios = [];
            for ($min = $minInicio; $min < $minFin; $min += $paso) {
                $inicio = $dia->copy()->startOfDay()->addMinutes($min);
                $fin    = $inicio->copy()->addMinutes($duracion);

                $estado = match (true) {
                    $ventanaCerrada || $inicio->lte($ahora)                     => self::SLOT_FUERA_DE_VENTANA,
                    $this->chocaCon($delProveedor, 'proveedor', $inicio, $fin) => self::SLOT_OCUPADO_PROVEEDOR,
                    $this->chocaCon($delComprador, 'comprador', $inicio, $fin) => self::SLOT_BLOQUEADO_COMPRADOR,
                    default                                                    => self::SLOT_LIBRE,
                };

                // Cita propia (con este u otro proveedor) que cae justo en el horario.
                $miCita = $delComprador->first(fn (Cita $c) => $c->inicio->lt($fin) && $c->fin->gt($inicio));

                $horarios[] = [
                    'hora'       => $inicio->format('H:i'),
                    'inicio'     => $inicio->toIso8601String(),
                    'fin'        => $fin->toIso8601String(),
                    'estado'     => $estado,
                    'mi_cita_id' => $miCita?->id,
                ];
            }

            $dias[] = ['fecha' => $dia->format('Y-m-d'), 'horarios' => $horarios];
        }

        return $dias;
    }

    /** CitaPublicaController::destroy: el comprador cancela su cita. */
    public function cancelarComoComprador(Cita $cita): Cita
    {
        if (in_array($cita->estado, self::ESTADOS_NO_CANCELABLES)) {
            throw new ReglaDeNegocioException('No puedes cancelar esta cita.');
        }

        $cita->load(['restaurantero', 'cliente']);
        $cita->update(['estado' => 'cancelada']);

        try {
            Mail::to($cita->cliente->email)->send(new CitaCancelada($cita));
        } catch (\Exception $e) {
            // No bloqueamos la operación si el correo falla
        }

        return $cita;
    }

    public function actualizarNotas(Cita $cita, ?string $notas): Cita
    {
        $cita->update(['notas' => $notas]);

        return $cita;
    }

    private function asegurarPropuestaPendiente(Cita $cita): void
    {
        if ($cita->estado !== 'pendiente_reconfirmacion' || !$cita->propuesta_inicio) {
            throw new ReglaDeNegocioException('Esta cita no tiene una propuesta de cambio de horario pendiente.');
        }
    }

    /**
     * El comprador acepta el nuevo horario propuesto por el proveedor (enlace del
     * correo o app). Si el hueco ya se ocupo, la cita queda como estaba, se avisa
     * al proveedor y se lanza ReglaDeNegocioException.
     */
    public function aceptarReagenda(Cita $cita): Cita
    {
        $this->asegurarPropuestaPendiente($cita);

        try {
            $this->confirmarReagenda($cita);
        } catch (ReglaDeNegocioException $e) {
            // El hueco se ocupo mientras la propuesta esperaba respuesta. La cita
            // queda como estaba y el proveedor puede proponer otro horario.
            $cita->loadMissing('restaurantero');
            if ($cita->restaurantero?->user_id) {
                Notificacion::crear(
                    $cita->restaurantero->user_id,
                    'cita_reagendada',
                    'Reagenda no aplicada',
                    'El comprador aceptó tu propuesta, pero ese horario ya estaba ocupado. Propón otro para la cita del ' .
                    $cita->inicio->format('d/m/Y H:i') . '.',
                    $cita->id
                );
            }

            throw new ReglaDeNegocioException('El horario propuesto ya no está disponible. Tu cita conserva su horario original; el proveedor te propondrá otro.');
        }

        $cita->load(['restaurantero', 'cliente']);

        try {
            Mail::to($cita->restaurantero->user->email)->send(new CitaReagendada($cita, 'proveedor'));
        } catch (\Throwable $e) {
            Log::warning('Error enviando correo de confirmación de reagenda al proveedor: ' . $e->getMessage());
        }

        return $cita;
    }

    /** El comprador rechaza el nuevo horario: la cita queda CANCELADA. */
    public function rechazarReagenda(Cita $cita): Cita
    {
        $this->asegurarPropuestaPendiente($cita);

        $cita->update([
            'estado' => 'cancelada',
            'token_confirmacion' => null,
        ]);

        $cita->load(['restaurantero', 'cliente']);

        try {
            // El comprador rechazo: la cita quedo CANCELADA. Antes se mandaba
            // el mismo correo que en la aceptacion y el proveedor no podia
            // distinguir un caso del otro.
            Mail::to($cita->restaurantero->user->email)->send(new CitaCancelada($cita));
        } catch (\Throwable $e) {
            Log::warning('Error notificando cancelacion al proveedor: ' . $e->getMessage());
        }

        // Sin el operador seguro, un restaurantero sin user_id lanzaba \Error
        // aqui y la ruta publica devolvia 500.
        if ($cita->restaurantero?->user_id) {
            Notificacion::crear(
                $cita->restaurantero->user_id,
                'cita_cancelada',
                'Propuesta de reagenda rechazada',
                'El comprador rechazo la nueva fecha propuesta. La cita del ' .
                $cita->inicio->format('d/m/Y H:i') . ' quedo cancelada.'
            );
        }

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
