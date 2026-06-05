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
                'name'     => $cita->cliente->name ?? 'N/A',
                'email'    => $cita->cliente->email ?? 'N/A',
                'telefono' => $cita->cliente->telefono ?? 'N/A',
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

        return Inertia::render('Restaurantero/Panel', [
            'restaurantero'   => $restaurantero,
            'citasHoy'        => $citasHoy,
            'citasSemana'     => $citasSemana,
            'citasPendientes' => $citasPendientes,
            'todasLasCitas'   => $todasLasCitas,
            'categorias'      => \App\Models\Restaurantero::$categorias,
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
