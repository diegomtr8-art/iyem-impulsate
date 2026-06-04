<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Cita;
use App\Models\Restaurantero;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CalendarioController extends Controller
{
    public function index()
    {
        return Inertia::render('Admin/Calendario/Index', [
            'restauranteros' => Restaurantero::select('id', 'nombre_restaurante')->orderBy('nombre_restaurante')->get(),
        ]);
    }

    public function events(Request $request)
    {
        $query = Cita::with(['restaurantero', 'cliente', 'servicio']);

        if ($request->filled('restaurantero_id')) {
            $query->where('restaurantero_id', $request->restaurantero_id);
        }

        if ($request->filled('start')) {
            $query->where('inicio', '>=', $request->start);
        }

        if ($request->filled('end')) {
            $query->where('inicio', '<=', $request->end);
        }

        $eventos = $query->get()->map(function (Cita $cita) {
            return [
                'id'    => $cita->id,
                'title' => ($cita->cliente->name ?? 'Cliente') . ' — ' . ($cita->servicio->nombre ?? 'Servicio'),
                'start' => $cita->inicio->toIso8601String(),
                'end'   => $cita->fin->toIso8601String(),
                'color' => match ($cita->estado) {
                    'confirmada' => '#22c55e',
                    'cancelada'  => '#ef4444',
                    'completada' => '#6366f1',
                    default      => '#f59e0b',
                },
                'extendedProps' => [
                    'estado'        => $cita->estado,
                    'cliente'       => $cita->cliente->name ?? '',
                    'clienteEmail'  => $cita->cliente->email ?? '',
                    'restaurantero' => $cita->restaurantero->nombre_restaurante ?? '',
                    'servicio'      => $cita->servicio->nombre ?? '',
                    'notas'         => $cita->notas,
                ],
            ];
        });

        return response()->json($eventos);
    }
}
