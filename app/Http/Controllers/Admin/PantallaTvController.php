<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Cita;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PantallaTvController extends Controller
{
    private static string $TOKEN_CONFIG_KEY = 'tv_access_token';

    public static function getToken(): string
    {
        return config('app.tv_token', 'impulsate-tv-2026');
    }

    public function index(Request $request, string $token)
    {
        if ($token !== static::getToken()) {
            abort(404);
        }

        return Inertia::render('Admin/PantallaTv', [
            'tvToken' => $token,
        ]);
    }

    public function citasActivas(Request $request, string $token)
    {
        if ($token !== static::getToken()) {
            abort(404);
        }

        $hoy = now()->toDateString();

        $citas = Cita::whereDate('inicio', $hoy)
            ->whereNotIn('estado', ['cancelada', 'rechazada'])
            ->with(['cliente', 'restaurantero'])
            ->orderBy('inicio')
            ->get()
            ->map(function ($cita) {
                $ahora = now();
                $enCurso = $ahora->between($cita->inicio, $cita->fin);

                return [
                    'id'          => $cita->id,
                    'hora_inicio' => $cita->inicio->format('H:i'),
                    'hora_fin'    => $cita->fin->format('H:i'),
                    'comprador'   => $cita->cliente?->name ?? '—',
                    'proveedor'   => $cita->restaurantero?->nombre_restaurante ?? '—',
                    'estado'      => $enCurso ? 'en_curso' : $cita->estado,
                    'estado_real' => $cita->estado,
                ];
            });

        return response()->json([
            'citas'      => $citas,
            'updated_at' => now()->format('H:i:s'),
        ]);
    }
}
