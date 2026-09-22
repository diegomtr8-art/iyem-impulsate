<?php

namespace App\Services\Admin;

use App\Models\Cita;
use App\Models\Restaurantero;
use App\Models\User;

/** KPIs del tablero del admin. Lo usan AdminController::dashboard (web) y la API v1 (/admin/dashboard). */
class AdminDashboardService
{
    public function kpis(): array
    {
        return [
            'stats' => [
                'totalUsuarios'       => User::count(),
                'totalRestauranteros' => Restaurantero::count(),
                'totalCitas'          => Cita::count(),
                'citasHoy'            => Cita::whereDate('inicio', today())->count(),
            ],
            'citasPorEstado' => Cita::selectRaw('estado, count(*) as total')
                ->groupBy('estado')
                ->pluck('total', 'estado'),
            'citasUltimos7Dias' => Cita::selectRaw('DATE(inicio) as fecha, count(*) as total')
                ->where('inicio', '>=', now()->subDays(6)->startOfDay())
                ->groupBy('fecha')
                ->orderBy('fecha')
                ->get(),
        ];
    }
}
