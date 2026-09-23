<?php

namespace App\Services\Admin;

use App\Models\Cita;
use App\Models\Evento;
use Carbon\Carbon;
use Illuminate\Support\Str;

/**
 * Datos de la Pantalla TV publica de un evento (citas de HOY en curso,
 * llamando, proximas y recientes). Lo usan PantallaTvController (polling
 * publico por token) y la API v1 del admin (vista previa en la app).
 */
class PantallaTvService
{
    public function eventoPorToken(string $token): ?Evento
    {
        if ($token === '') return null;

        return Evento::where('tv_token', $token)->first();
    }

    public function datos(Evento $evento): array
    {
        $hoy   = now()->toDateString();
        $ahora = now();

        $citas = Cita::whereBetween('inicio', [
                Carbon::parse($hoy)->startOfDay(),
                Carbon::parse($hoy)->endOfDay(),
            ])
            ->where('edicion_id', $evento->id)
            ->whereNotIn('estado', ['cancelada', 'rechazada'])
            ->whereNotIn('estado_tv', ['pendiente'])
            ->with(['cliente', 'restaurantero'])
            ->orderBy('inicio')
            ->get();

        $enCurso = $citas
            ->filter(fn ($c) => $c->estado_tv === 'en_curso')
            ->map(fn ($c) => $this->mapPublico($c, $ahora))
            ->values();

        $llamando = $citas
            ->filter(fn ($c) => $c->estado_tv === 'llamando')
            ->map(fn ($c) => $this->mapPublico($c, $ahora))
            ->values();

        $proximas = Cita::whereBetween('inicio', [
                Carbon::parse($hoy)->startOfDay(),
                Carbon::parse($hoy)->endOfDay(),
            ])
            ->where('edicion_id', $evento->id)
            ->whereIn('estado', ['confirmada', 'reagendada'])
            ->whereIn('estado_tv', ['pendiente', 'reprogramada'])
            ->with(['cliente', 'restaurantero'])
            ->where('inicio', '>=', $ahora)
            ->orderBy('inicio')
            ->limit(8)
            ->get()
            ->map(fn ($c) => [
                'id'         => $c->id,
                'hora'       => $c->inicio->format('H:i'),
                'mesa'       => $c->mesa ?? '—',
                'comprador'  => $this->comprador($c),
                'proveedor'  => $c->restaurantero?->nombre_restaurante ?? '—',
                'categoria'  => $c->restaurantero?->categoria ?? '',
            ])
            ->values();

        $recientes = $citas
            ->filter(fn ($c) => $c->estado_tv === 'finalizada')
            ->sortByDesc('hora_real_fin')
            ->take(5)
            ->map(fn ($c) => [
                'id'        => $c->id,
                'hora'      => $c->hora_real_fin?->format('H:i') ?? $c->fin->format('H:i'),
                'mesa'      => $c->mesa ?? '—',
                'comprador' => $this->comprador($c),
                'proveedor' => $c->restaurantero?->nombre_restaurante ?? '—',
            ])
            ->values();

        return [
            'en_curso'   => $enCurso,
            'llamando'   => $llamando,
            'proximas'   => $proximas,
            'recientes'  => $recientes,
            'timestamp'  => $ahora->format('H:i:s'),
            'fecha'      => $ahora->locale('es')->isoFormat('dddd D [de] MMMM, YYYY'),
        ];
    }

    private function comprador(Cita $c): string
    {
        return (string) Str::of($c->cliente?->nombre_empresa ?: $c->cliente?->name ?: '—')->limit(28);
    }

    private function mapPublico(Cita $c, $ahora): array
    {
        $inicioReal = $c->hora_real_inicio ?? $c->inicio;
        $elapsed    = $inicioReal->diffInSeconds($ahora);
        $durTotal   = $c->inicio->diffInSeconds($c->fin);

        return [
            'id'            => $c->id,
            'hora_inicio'   => $c->inicio->format('H:i'),
            'hora_fin'      => $c->fin->format('H:i'),
            'hora_real'     => $inicioReal->format('H:i'),
            'mesa'          => $c->mesa ?? '—',
            'comprador'     => $this->comprador($c),
            'proveedor'     => $c->restaurantero?->nombre_restaurante ?? '—',
            'categoria'     => $c->restaurantero?->categoria ?? '',
            'elapsed_sec'   => max(0, $elapsed),
            'duracion_sec'  => max(1, $durTotal),
            'llamada_ts'    => $c->llamada_at?->timestamp,
            'estado_tv'     => $c->estado_tv,
        ];
    }
}
