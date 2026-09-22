<?php

namespace App\Services;

use App\Exceptions\ReglaDeNegocioException;
use App\Mail\CitaAceptada;
use App\Mail\CitaReagendada;
use App\Mail\CitaRechazada;
use App\Models\Cita;
use App\Models\Notificacion;
use App\Models\Restaurantero;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Citas RECIBIDAS por el proveedor: pestanas y contadores del listado, KPIs del
 * panel, feed del calendario y las respuestas (aceptar, rechazar, proponer otro
 * horario) con sus correos y notificaciones. Lo usan RestauranteroCitasController,
 * RestauranteroPanelController y la API v1 (/api/v1/proveedor/*).
 */
class GestionCitasProveedorService
{
    public function __construct(private AgendaCitaService $agenda) {}

    /** Estados de cada pestana de /proveedor/citas. Una pestana desconocida muestra todas. */
    public const PESTANAS = [
        'pendientes'  => ['pendiente'],
        'confirmadas' => ['confirmada', 'pendiente_reconfirmacion', 'reagendada'],
        'rechazadas'  => ['rechazada', 'cancelada'],
        'todas'       => ['pendiente', 'confirmada', 'cancelada', 'completada', 'rechazada', 'reagendada', 'pendiente_reconfirmacion'],
    ];

    /** Validacion de POST /proveedor/citas/{cita}/reagendar (web y API). */
    public const REGLAS_REAGENDAR = [
        'propuesta_fecha' => ['required', 'date', 'after_or_equal:today'],
        'propuesta_hora'  => ['required', 'date_format:H:i'],
    ];

    /** Colores del calendario del panel (FullCalendar). */
    public const COLORES_CALENDARIO = [
        'pendiente'  => '#f59e0b',
        'confirmada' => '#10b981',
        'cancelada'  => '#6b7280',
        'completada' => '#3b82f6',
    ];
    public const COLOR_POR_DEFECTO = '#6b7280';

    /** Botones que la web muestra por estado (Restaurantero/Panel.vue). */
    private const ESTADOS_ACEPTABLES   = ['pendiente'];
    private const ESTADOS_REAGENDABLES = ['pendiente', 'confirmada', 'reagendada'];

    public function estadosDePestana(?string $pestana): array
    {
        return self::PESTANAS[$pestana] ?? self::PESTANAS['todas'];
    }

    public function citasDePestana(Restaurantero $r, ?string $pestana): Builder
    {
        return Cita::where('restaurantero_id', $r->id)
            ->whereIn('estado', $this->estadosDePestana($pestana))
            ->orderByDesc('inicio');
    }

    /** @return array{pendientes: int, confirmadas: int, rechazadas: int} */
    public function contadores(Restaurantero $r): array
    {
        return [
            'pendientes'  => Cita::where('restaurantero_id', $r->id)->where('estado', 'pendiente')->count(),
            'confirmadas' => Cita::where('restaurantero_id', $r->id)->whereIn('estado', self::PESTANAS['confirmadas'])->count(),
            'rechazadas'  => Cita::where('restaurantero_id', $r->id)->whereIn('estado', self::PESTANAS['rechazadas'])->count(),
        ];
    }

    /**
     * KPIs del panel web.
     *
     * @return array{citas_hoy: int, citas_semana: int, citas_pendientes: int, total_en_evento: int, tasa_aceptacion: ?float}
     */
    public function kpis(Restaurantero $r): array
    {
        $hoy       = Carbon::today();
        $finSemana = Carbon::today()->endOfWeek();

        $totalConfirmadas = Cita::where('restaurantero_id', $r->id)->where('estado', 'confirmada')->count();
        $totalRechazadas  = Cita::where('restaurantero_id', $r->id)->where('estado', 'rechazada')->count();

        return [
            'citas_hoy'        => Cita::where('restaurantero_id', $r->id)->whereDate('inicio', $hoy)->count(),
            'citas_semana'     => Cita::where('restaurantero_id', $r->id)->whereBetween('inicio', [$hoy, $finSemana])->count(),
            'citas_pendientes' => Cita::where('restaurantero_id', $r->id)->where('estado', 'pendiente')->count(),
            'total_en_evento'  => Cita::where('restaurantero_id', $r->id)->whereNotIn('estado', ['cancelada', 'rechazada'])->count(),
            'tasa_aceptacion'  => ($totalConfirmadas + $totalRechazadas) > 0
                ? round(($totalConfirmadas / ($totalConfirmadas + $totalRechazadas)) * 100)
                : null,
        ];
    }

    /** "Todas las citas" del panel: pendientes primero, luego por fecha (maximo $limite). */
    public function citasDelPanel(Restaurantero $r, int $limite = 50)
    {
        return Cita::where('restaurantero_id', $r->id)
            ->with('cliente')
            ->orderByRaw("FIELD(estado, 'pendiente', 'confirmada', 'reagendada', 'pendiente_reconfirmacion', 'completada', 'cancelada', 'rechazada')")
            ->orderBy('inicio', 'desc')
            ->take($limite)
            ->get();
    }

    /** Cita pendiente o confirmada que empieza en las proximas 2 horas (aviso del panel). */
    public function citaProxima2h(Restaurantero $r): ?Cita
    {
        return Cita::where('restaurantero_id', $r->id)
            ->whereIn('estado', ['confirmada', 'pendiente'])
            ->whereBetween('inicio', [Carbon::now(), Carbon::now()->addHours(2)])
            ->with('cliente')
            ->orderBy('inicio')
            ->first();
    }

    /** Citas del calendario: todas menos las canceladas (la web no filtra por rango; la API si puede). */
    public function citasCalendario(Restaurantero $r, ?Carbon $desde = null, ?Carbon $hasta = null)
    {
        return Cita::where('restaurantero_id', $r->id)
            ->with('cliente')
            ->whereNotIn('estado', ['cancelada'])
            ->when($desde, fn ($q) => $q->where('fin', '>', $desde))
            ->when($hasta, fn ($q) => $q->where('inicio', '<', $hasta))
            ->get();
    }

    public function colorCalendario(string $estado): string
    {
        return self::COLORES_CALENDARIO[$estado] ?? self::COLOR_POR_DEFECTO;
    }

    /**
     * Lo que el proveedor puede hacer con la cita (los botones de la web).
     *
     * @return array{puede_aceptar: bool, puede_rechazar: bool, puede_reagendar: bool}
     */
    public function acciones(Cita $cita): array
    {
        return [
            'puede_aceptar'   => in_array($cita->estado, self::ESTADOS_ACEPTABLES),
            'puede_rechazar'  => in_array($cita->estado, self::ESTADOS_ACEPTABLES),
            'puede_reagendar' => in_array($cita->estado, self::ESTADOS_REAGENDABLES),
        ];
    }

    /**
     * La web solo ofrece los botones segun el estado, pero sus rutas no lo
     * comprueban. La API si: 422 si la accion no aplica.
     */
    public function asegurarAccion(Cita $cita, string $accion): void
    {
        if (!($this->acciones($cita)['puede_' . $accion] ?? false)) {
            $textos = [
                'aceptar'   => 'Solo puedes aceptar citas pendientes.',
                'rechazar'  => 'Solo puedes rechazar citas pendientes.',
                'reagendar' => 'Solo puedes proponer otro horario para citas pendientes o confirmadas.',
            ];
            throw new ReglaDeNegocioException($textos[$accion] ?? 'Esta acción no aplica a la cita.', 'estado');
        }
    }

    public function aceptar(Cita $cita): Cita
    {
        $cita->update(['estado' => 'confirmada']);

        $cita->load(['cliente', 'restaurantero']);

        try {
            Mail::to($cita->cliente->email)->send(new CitaAceptada($cita));
        } catch (\Throwable $e) {
            Log::warning('Error enviando correo de cita aceptada al comprador: ' . $e->getMessage());
        }

        $fechaFmt = $cita->inicio->format('d/m/Y H:i');
        Notificacion::crear(
            $cita->cliente_id,
            'cita_aceptada',
            'Cita confirmada',
            "Tu cita con {$cita->restaurantero->nombre_restaurante} el {$fechaFmt} fue confirmada.",
            $cita->id
        );

        return $cita;
    }

    public function rechazar(Cita $cita): Cita
    {
        $cita->update(['estado' => 'rechazada']);

        $cita->load(['cliente', 'restaurantero']);

        try {
            Mail::to($cita->cliente->email)->send(new CitaRechazada($cita, 'cliente'));
        } catch (\Throwable $e) {
            Log::warning('Error enviando correo de cita rechazada al comprador: ' . $e->getMessage());
        }

        $fechaFmt = $cita->inicio->format('d/m/Y H:i');
        Notificacion::crear(
            $cita->cliente_id,
            'cita_rechazada',
            'Cita rechazada',
            "Tu cita con {$cita->restaurantero->nombre_restaurante} el {$fechaFmt} fue rechazada.",
            $cita->id
        );

        return $cita;
    }

    /**
     * El proveedor propone otro horario. $datos ya validado con REGLAS_REAGENDAR.
     * Misma regla de duracion que el alta, y el hueco propuesto debe estar libre
     * para ambas partes (si no, ReglaDeNegocioException en 'fecha').
     */
    public function proponerReagenda(Cita $cita, string $fecha, string $hora): Cita
    {
        $propuestaInicio = Carbon::parse($fecha . ' ' . $hora);
        $propuestaFin    = $this->agenda->proponerReagenda($cita, $propuestaInicio);
        $token           = Str::random(64);

        $cita->update([
            'estado'             => 'pendiente_reconfirmacion',
            'propuesta_inicio'   => $propuestaInicio,
            'propuesta_fin'      => $propuestaFin,
            'token_confirmacion' => $token,
        ]);

        $cita->load(['cliente', 'restaurantero', 'servicio']);

        try {
            Mail::to($cita->cliente->email)->send(new CitaReagendada($cita, 'cliente'));
        } catch (\Throwable $e) {
            Log::warning('Error enviando correo de propuesta de reagenda al comprador: ' . $e->getMessage());
        }

        $fechaNueva = $propuestaInicio->format('d/m/Y H:i');
        Notificacion::crear(
            $cita->cliente_id,
            'cita_reagendada',
            'Propuesta de cambio de horario',
            "{$cita->restaurantero->nombre_restaurante} propone mover tu cita al {$fechaNueva}. Revisa tu correo para confirmar.",
            $cita->id
        );

        return $cita;
    }
}
