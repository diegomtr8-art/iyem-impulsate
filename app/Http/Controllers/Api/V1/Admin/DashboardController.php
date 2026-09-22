<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\AdminEventoResource;
use App\Models\Evento;
use App\Models\Restaurantero;
use App\Services\Admin\AdminDashboardService;
use App\Services\Admin\AdminProveedoresService;
use Illuminate\Support\Facades\DB;

/** Tablero del admin: los mismos KPIs que /admin de la web. */
class DashboardController extends Controller
{
    public function dashboard(AdminDashboardService $dashboard, AdminProveedoresService $proveedores)
    {
        $kpis = $dashboard->kpis();

        return response()->json(['data' => [
            'totales' => [
                'usuarios'    => $kpis['stats']['totalUsuarios'],
                'proveedores' => $kpis['stats']['totalRestauranteros'],
                'citas'       => $kpis['stats']['totalCitas'],
                'citas_hoy'   => $kpis['stats']['citasHoy'],
            ],
            'citas_por_estado'     => $kpis['citasPorEstado']->map(fn ($n) => (int) $n),
            'citas_ultimos_7_dias' => $kpis['citasUltimos7Dias']->map(fn ($f) => [
                'fecha' => $f->fecha,
                'total' => (int) $f->total,
            ])->values(),
            // Contadores del menu lateral de la web (HandleInertiaRequests::pendientesAprobacion
            // y la insignia de solicitudes de cada evento).
            'pendientes' => [
                'proveedores_por_aprobar' => Restaurantero::where('aprobado', false)->where('rechazado', false)->count(),
                'proveedores_solicitaron' => $proveedores->contarPendientesAprobacion(),
                'solicitudes_eventos'     => DB::table('evento_usuario')->where('estado', 'pendiente')->count(),
            ],
        ]]);
    }

    /**
     * Selector de evento de contexto. La web lo guarda en la sesion
     * (POST /admin/eventos/contexto); la API no tiene sesion: la app guarda el
     * id elegido y lo manda como evento_id en citas, agenda y torre. Si no lo
     * manda, se usa `predeterminado_id` (el mismo que usa la web sin eleccion).
     */
    public function contexto()
    {
        $activos = Evento::queryActivos()->get();

        return response()->json(['data' => [
            'predeterminado_id' => $activos->first()?->id,
            'eventos_activos'   => $activos->map(fn (Evento $e) => [
                'id'                => $e->id,
                'nombre'            => $e->nombre,
                'tipo_evento'       => $e->tipo_evento ?? 'encuentro_negocios',
                'destacado'         => (bool) $e->destacado,
                'fecha_hora_inicio' => $e->fecha_hora_inicio?->toIso8601String(),
                'fecha_hora_fin'    => $e->fecha_hora_fin?->toIso8601String(),
            ])->values(),
        ]]);
    }
}
