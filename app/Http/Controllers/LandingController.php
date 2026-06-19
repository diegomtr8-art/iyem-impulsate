<?php

namespace App\Http\Controllers;

use App\Models\Evento;
use Inertia\Inertia;

class LandingController extends Controller
{
    public function index()
    {
        $eventosCarrusel = Evento::whereNotNull('imagen_carrusel')
            ->where(function ($q) {
                $q->where('activa', true)
                  ->orWhere('fecha_hora_inicio', '>=', now());
            })
            ->orderBy('fecha_hora_inicio')
            ->get()
            ->map(fn ($ev) => [
                'id'     => $ev->id,
                'nombre' => $ev->nombre,
                'sector' => $ev->sector_economico,
                'fecha'  => $ev->fecha_hora_inicio?->translatedFormat('F Y'),
                'img'    => $ev->imagen_carrusel_url,
            ]);

        return Inertia::render('Welcome', [
            'canLogin'        => \Illuminate\Support\Facades\Route::has('login'),
            'canRegister'     => \Illuminate\Support\Facades\Route::has('register'),
            'eventosCarrusel' => $eventosCarrusel,
        ]);
    }
}
