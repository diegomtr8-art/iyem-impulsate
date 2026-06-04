<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Cita;
use App\Models\Restaurantero;
use App\Models\User;
use Inertia\Inertia;

class AdminController extends Controller
{
    public function dashboard()
    {
        $totalUsuarios       = User::count();
        $totalRestauranteros = Restaurantero::count();
        $totalCitas          = Cita::count();
        $citasHoy            = Cita::whereDate('inicio', today())->count();

        $citasPorEstado = Cita::selectRaw('estado, count(*) as total')
            ->groupBy('estado')
            ->pluck('total', 'estado');

        $citasUltimos7Dias = Cita::selectRaw('DATE(inicio) as fecha, count(*) as total')
            ->where('inicio', '>=', now()->subDays(6)->startOfDay())
            ->groupBy('fecha')
            ->orderBy('fecha')
            ->get();

        return Inertia::render('Admin/Dashboard', [
            'stats' => [
                'totalUsuarios'       => $totalUsuarios,
                'totalRestauranteros' => $totalRestauranteros,
                'totalCitas'          => $totalCitas,
                'citasHoy'            => $citasHoy,
            ],
            'citasPorEstado'    => $citasPorEstado,
            'citasUltimos7Dias' => $citasUltimos7Dias,
        ]);
    }
}
