<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;

class MetricasController extends Controller
{
    public function index()
    {
        if (!Schema::hasTable('page_visits')) {
            return Inertia::render('Admin/Metricas', [
                'sinDatos'        => true,
                'totalVisitas'    => 0,
                'visitasHoy'      => 0,
                'visitasSemana'   => 0,
                'visitasPorDia'   => [],
                'visitasPorPagina'=> [],
                'porDispositivo'  => [],
                'proveedoresTop'  => [],
            ]);
        }

        $totalVisitas  = DB::table('page_visits')->count();
        $visitasHoy    = DB::table('page_visits')->whereDate('created_at', today())->count();
        $visitasSemana = DB::table('page_visits')->where('created_at', '>=', now()->startOfWeek())->count();

        $visitasPorDia = DB::table('page_visits')
            ->selectRaw('DATE(created_at) as fecha, count(*) as total')
            ->where('created_at', '>=', now()->subDays(29)->startOfDay())
            ->groupBy('fecha')
            ->orderBy('fecha')
            ->get();

        $visitasPorPagina = DB::table('page_visits')
            ->selectRaw('url as pagina, count(*) as total')
            ->groupBy('url')
            ->orderByDesc('total')
            ->limit(10)
            ->get();

        $porDispositivo = DB::table('page_visits')
            ->selectRaw('device_type, count(*) as total')
            ->groupBy('device_type')
            ->orderByDesc('total')
            ->get();

        $proveedoresTop = DB::table('page_visits')
            ->join('restauranteros', 'page_visits.restaurantero_id', '=', 'restauranteros.id')
            ->selectRaw('restauranteros.id, restauranteros.nombre_restaurante as nombre, restauranteros.logo_path, count(*) as total')
            ->whereNotNull('page_visits.restaurantero_id')
            ->groupBy('restauranteros.id', 'restauranteros.nombre_restaurante', 'restauranteros.logo_path')
            ->orderByDesc('total')
            ->limit(10)
            ->get();

        return Inertia::render('Admin/Metricas', [
            'sinDatos'         => false,
            'totalVisitas'     => $totalVisitas,
            'visitasHoy'       => $visitasHoy,
            'visitasSemana'    => $visitasSemana,
            'visitasPorDia'    => $visitasPorDia,
            'visitasPorPagina' => $visitasPorPagina,
            'porDispositivo'   => $porDispositivo,
            'proveedoresTop'   => $proveedoresTop,
        ]);
    }
}
