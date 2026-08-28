<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Cita;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PantallaTvController extends Controller
{
    /**
     * El token vive en la fila del evento, no en una constante del codigo.
     * Antes era un literal publicado en DEPLOY.md y en el repo.
     */
    public static function getToken(): string
    {
        return \App\Models\Evento::contextoAdmin()?->tokenTv() ?? '';
    }

    /** Resuelve el evento a partir del token recibido. */
    private static function eventoPorToken(string $token): ?\App\Models\Evento
    {
        if ($token === '') return null;
        return \App\Models\Evento::where('tv_token', $token)->first();
    }

    public function index(Request $request, string $token)
    {
        $evento = static::eventoPorToken($token);
        abort_if(!$evento, 404);
        return Inertia::render('Admin/PantallaTv', [
            'tvToken' => $token,
        ]);
    }

    /** Datos públicos para la Pantalla Pública (polling 3s) */
    public function datosPublicos(Request $request, string $token)
    {
        $evento = static::eventoPorToken($token);
        abort_if(!$evento, 404);

        $hoy  = now()->toDateString();
        $ahora = now();

        $citas = Cita::whereDate('inicio', $hoy)
            ->where('edicion_id', $evento->id)
            ->whereNotIn('estado', ['cancelada', 'rechazada'])
            ->whereNotIn('estado_tv', ['pendiente'])
            ->with(['cliente', 'restaurantero'])
            ->orderBy('inicio')
            ->get();

        $enCurso = $citas
            ->filter(fn($c) => $c->estado_tv === 'en_curso')
            ->map(fn($c) => $this->mapPublico($c, $ahora))
            ->values();

        $llamando = $citas
            ->filter(fn($c) => $c->estado_tv === 'llamando')
            ->map(fn($c) => $this->mapPublico($c, $ahora))
            ->values();

        $proximas = Cita::whereDate('inicio', $hoy)
            ->where('edicion_id', $evento->id)
            ->whereIn('estado', ['confirmada', 'reagendada'])
            ->whereIn('estado_tv', ['pendiente', 'reprogramada'])
            ->with(['cliente', 'restaurantero'])
            ->where('inicio', '>=', $ahora)
            ->orderBy('inicio')
            ->limit(8)
            ->get()
            ->map(fn($c) => [
                'id'         => $c->id,
                'hora'       => $c->inicio->format('H:i'),
                'mesa'       => $c->mesa ?? '—',
                'comprador'  => (string) \Illuminate\Support\Str::of($c->cliente?->nombre_empresa ?: $c->cliente?->name ?: '—')->limit(28),
                'proveedor'  => $c->restaurantero?->nombre_restaurante ?? '—',
                'categoria'  => $c->restaurantero?->categoria ?? '',
            ])
            ->values();

        $recientes = $citas
            ->filter(fn($c) => $c->estado_tv === 'finalizada')
            ->sortByDesc('hora_real_fin')
            ->take(5)
            ->map(fn($c) => [
                'id'        => $c->id,
                'hora'      => $c->hora_real_fin?->format('H:i') ?? $c->fin->format('H:i'),
                'mesa'      => $c->mesa ?? '—',
                'comprador' => (string) \Illuminate\Support\Str::of($c->cliente?->nombre_empresa ?: $c->cliente?->name ?: '—')->limit(28),
                'proveedor' => $c->restaurantero?->nombre_restaurante ?? '—',
            ])
            ->values();

        return response()->json([
            'en_curso'   => $enCurso,
            'llamando'   => $llamando,
            'proximas'   => $proximas,
            'recientes'  => $recientes,
            'timestamp'  => $ahora->format('H:i:s'),
            'fecha'      => $ahora->locale('es')->isoFormat('dddd D [de] MMMM, YYYY'),
        ]);
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
            'comprador'     => (string) \Illuminate\Support\Str::of($c->cliente?->nombre_empresa ?: $c->cliente?->name ?: '—')->limit(28),
            'proveedor'     => $c->restaurantero?->nombre_restaurante ?? '—',
            'categoria'     => $c->restaurantero?->categoria ?? '',
            'elapsed_sec'   => max(0, $elapsed),
            'duracion_sec'  => max(1, $durTotal),
            'llamada_ts'    => $c->llamada_at?->timestamp,
            'estado_tv'     => $c->estado_tv,
        ];
    }
}
