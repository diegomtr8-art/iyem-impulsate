<?php

namespace App\Http\Controllers;

use App\Models\Cita;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Carbon\Carbon;

class RestauranteroPanelController extends Controller
{
    public function index(Request $request)
    {
        $restaurantero = $request->user()->restaurantero;

        if (!$restaurantero) {
            return redirect()->route('restaurantero.completar-perfil')
                ->with('warning', 'Debes completar tu perfil de proveedor antes de acceder al panel.');
        }

        $hoy       = Carbon::today();
        $finSemana = Carbon::today()->endOfWeek();

        $citasHoy        = Cita::where('restaurantero_id', $restaurantero->id)->whereDate('inicio', $hoy)->count();
        $citasSemana     = Cita::where('restaurantero_id', $restaurantero->id)->whereBetween('inicio', [$hoy, $finSemana])->count();
        $citasPendientes = Cita::where('restaurantero_id', $restaurantero->id)->where('estado', 'pendiente')->count();

        $mapCita = fn($cita) => [
            'id'               => $cita->id,
            'inicio'           => $cita->inicio->toISOString(),
            'fin'              => $cita->fin->toISOString(),
            'estado'           => $cita->estado,
            'notas'            => $cita->notas,
            'propuesta_inicio' => $cita->propuesta_inicio?->toISOString(),
            'cliente' => [
                'name'        => $cita->cliente->name ?? 'N/A',
                'email'       => $cita->cliente->email ?? 'N/A',
                'telefono'    => $cita->cliente->telefono ?? 'N/A',
                'necesidades' => $cita->cliente->necesidades ?? null,
            ],
        ];

        // Todas las citas (ordenadas: pendientes primero, luego por fecha)
        $todasLasCitas = Cita::where('restaurantero_id', $restaurantero->id)
            ->with('cliente')
            ->orderByRaw("FIELD(estado, 'pendiente', 'confirmada', 'reagendada', 'pendiente_reconfirmacion', 'completada', 'cancelada', 'rechazada')")
            ->orderBy('inicio', 'desc')
            ->take(50)
            ->get()
            ->map($mapCita)
            ->values();

        $totalConfirmadas = Cita::where('restaurantero_id', $restaurantero->id)->where('estado', 'confirmada')->count();
        $totalRechazadas  = Cita::where('restaurantero_id', $restaurantero->id)->where('estado', 'rechazada')->count();
        $totalEnEvento    = Cita::where('restaurantero_id', $restaurantero->id)->whereNotIn('estado', ['cancelada', 'rechazada'])->count();
        $tasaAceptacion   = ($totalConfirmadas + $totalRechazadas) > 0
            ? round(($totalConfirmadas / ($totalConfirmadas + $totalRechazadas)) * 100)
            : null;

        $citaProxima2h = Cita::where('restaurantero_id', $restaurantero->id)
            ->whereIn('estado', ['confirmada', 'pendiente'])
            ->whereBetween('inicio', [Carbon::now(), Carbon::now()->addHours(2)])
            ->with('cliente')
            ->orderBy('inicio')
            ->first();

        $notificaciones = \App\Models\Notificacion::where('user_id', $request->user()->id)
            ->where('leida', false)
            ->orderByDesc('created_at')
            ->take(3)
            ->get(['id', 'tipo', 'titulo', 'mensaje', 'cita_id', 'created_at']);

        return Inertia::render('Restaurantero/Panel', [
            'restaurantero'   => $restaurantero,
            'citasHoy'        => $citasHoy,
            'citasSemana'     => $citasSemana,
            'citasPendientes' => $citasPendientes,
            'todasLasCitas'   => $todasLasCitas,
            'categorias'      => \App\Models\Restaurantero::$categorias,
            'tasaAceptacion'  => $tasaAceptacion,
            'totalEnEvento'   => $totalEnEvento,
            'citaProxima2h'   => $citaProxima2h ? [
                'id'           => $citaProxima2h->id,
                'inicio'       => $citaProxima2h->inicio->toISOString(),
                'cliente_name' => $citaProxima2h->cliente->name ?? 'Comprador',
            ] : null,
            'notificaciones'  => $notificaciones,
        ]);
    }

    public function eventos(Request $request)
    {
        $restaurantero = $request->user()->restaurantero;
        if (!$restaurantero) {
            return response()->json([]);
        }

        $colores = [
            'pendiente'  => '#f59e0b',
            'confirmada' => '#10b981',
            'cancelada'  => '#6b7280',
            'completada' => '#3b82f6',
        ];

        $eventos = Cita::where('restaurantero_id', $restaurantero->id)
            ->with('cliente')
            ->whereNotIn('estado', ['cancelada'])
            ->get()
            ->map(fn($cita) => [
                'id'              => $cita->id,
                'title'           => $cita->cliente->name ?? 'Cliente',
                'start'           => $cita->inicio,
                'end'             => $cita->fin,
                'backgroundColor' => $colores[$cita->estado] ?? '#6b7280',
                'borderColor'     => $colores[$cita->estado] ?? '#6b7280',
                'textColor'       => '#111827',
            ]);

        return response()->json($eventos);
    }
}
