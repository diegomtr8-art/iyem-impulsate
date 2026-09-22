<?php

namespace App\Services\Admin;

use App\Models\Cita;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Torre de control del dia del evento: citas de HOY con su estado en sala
 * (llamando, en curso, finalizada, ausente), mesa, llamada y horas reales.
 * Lo usan Admin\TorreControlController (web, polling cada 3 s) y la API v1.
 */
class TorreControlService
{
    public const MINUTOS_RETRASO = [5, 10, 15, 20];
    public const MESAS_POR_DEFECTO = 50;

    public function numMesas(): int
    {
        return (int) (DB::table('config_evento')->where('clave', 'num_mesas')->value('valor') ?? self::MESAS_POR_DEFECTO);
    }

    public function actualizarMesas(int $numMesas): void
    {
        DB::table('config_evento')->updateOrInsert(
            ['clave' => 'num_mesas'],
            ['valor' => $numMesas, 'updated_at' => now()]
        );
    }

    /** Todas las citas de hoy (de cualquier estado), opcionalmente de un evento. */
    private function delDia(?int $eventoId = null): Builder
    {
        $hoy = now()->toDateString();

        return Cita::whereBetween('inicio', [
                Carbon::parse($hoy)->startOfDay(),
                Carbon::parse($hoy)->endOfDay(),
            ])
            ->when($eventoId, fn ($q) => $q->where('edicion_id', $eventoId));
    }

    /** Citas vivas de hoy ya mapeadas, en orden de hora. */
    public function citasDelDia(?int $eventoId = null): Collection
    {
        return $this->delDia($eventoId)
            ->whereNotIn('estado', ['cancelada', 'rechazada'])
            ->with(['cliente', 'restaurantero'])
            ->orderBy('inicio')
            ->get()
            ->map(fn ($c) => $this->mapCita($c));
    }

    public function mesasOcupadas(Collection $citas): Collection
    {
        return $citas->whereIn('estado_tv', ['llamando', 'en_curso'])->pluck('mesa')->filter()->unique()->values();
    }

    /** Lo que devuelve GET /admin/torre/estado de la web. */
    public function estado(): array
    {
        $citas = $this->citasDelDia();

        return [
            'citas'          => $citas,
            'mesas_ocupadas' => $this->mesasOcupadas($citas),
            'timestamp'      => now()->format('H:i:s'),
        ];
    }

    /**
     * Ultima modificacion de CUALQUIER cita de hoy (incluidas las que se
     * cancelaron: al cancelarse salen del tablero y eso tambien es un cambio).
     */
    public function actualizadoAt(?int $eventoId = null): ?Carbon
    {
        $max = $this->delDia($eventoId)->max('updated_at');

        return $max ? Carbon::parse($max) : null;
    }

    /** ¿Alguna cita de hoy cambio en o despues de $desde? (precision de segundos) */
    public function huboCambiosDesde(Carbon $desde, ?int $eventoId = null): bool
    {
        return $this->delDia($eventoId)->where('updated_at', '>=', $desde->copy()->startOfSecond())->exists();
    }

    public function llamar(Cita $cita, string $mesa): Cita
    {
        $cita->update([
            'mesa'       => $mesa,
            'estado_tv'  => 'llamando',
            'llamada_at' => now(),
        ]);

        return $this->fresca($cita);
    }

    public function repetirAnuncio(Cita $cita): Cita
    {
        $cita->update(['llamada_at' => now(), 'estado_tv' => 'llamando']);

        return $this->fresca($cita);
    }

    public function iniciar(Cita $cita): Cita
    {
        $cita->update([
            'estado_tv'        => 'en_curso',
            'hora_real_inicio' => now(),
        ]);

        return $this->fresca($cita);
    }

    public function finalizar(Cita $cita): Cita
    {
        $cita->update([
            'estado_tv'     => 'finalizada',
            'hora_real_fin' => now(),
            'estado'        => 'completada',
        ]);

        return $this->fresca($cita);
    }

    public function ausente(Cita $cita): Cita
    {
        $cita->update(['estado_tv' => 'ausente']);

        return $this->fresca($cita);
    }

    public function retrasar(Cita $cita, int $minutos): Cita
    {
        $cita->update([
            'inicio' => $cita->inicio->addMinutes($minutos),
            'fin'    => $cita->fin->addMinutes($minutos),
        ]);

        return $this->fresca($cita);
    }

    public function cambiarMesa(Cita $cita, string $mesa): Cita
    {
        $cita->update(['mesa' => $mesa]);

        return $this->fresca($cita);
    }

    private function fresca(Cita $cita): Cita
    {
        return $cita->fresh(['cliente', 'restaurantero']);
    }

    public function mapCita(Cita $c): array
    {
        $duracionReal = null;
        if ($c->hora_real_inicio && $c->hora_real_fin) {
            $duracionReal = $c->hora_real_inicio->diffInMinutes($c->hora_real_fin);
        }

        return [
            'id'               => $c->id,
            'hora_prog'        => $c->inicio->format('H:i'),
            'hora_fin_prog'    => $c->fin->format('H:i'),
            'hora_real_inicio' => $c->hora_real_inicio?->format('H:i'),
            'hora_real_fin'    => $c->hora_real_fin?->format('H:i'),
            'llamada_at'       => $c->llamada_at?->format('H:i:s'),
            'llamada_ts'       => $c->llamada_at?->timestamp,
            'mesa'             => $c->mesa,
            'estado_tv'        => $c->estado_tv ?? 'pendiente',
            'estado'           => $c->estado,
            'duracion_real'    => $duracionReal,
            'comprador'        => $c->cliente?->name ?? '—',
            'comprador_tel'    => $c->cliente?->telefono ?? '',
            'proveedor'        => $c->restaurantero?->nombre_restaurante ?? '—',
            'categoria'        => $c->restaurantero?->categoria ?? '',
        ];
    }
}
