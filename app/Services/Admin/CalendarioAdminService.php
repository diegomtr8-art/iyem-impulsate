<?php

namespace App\Services\Admin;

use App\Models\Cita;
use App\Models\Restaurantero;
use Illuminate\Support\Collection;

/**
 * Calendario general de citas del admin (feed de FullCalendar).
 * Lo usan Admin\CalendarioController (web) y la API v1.
 */
class CalendarioAdminService
{
    public const COLORES = [
        'confirmada' => '#22c55e',
        'cancelada'  => '#ef4444',
        'completada' => '#6366f1',
    ];
    public const COLOR_POR_DEFECTO = '#f59e0b';

    public function proveedores(): Collection
    {
        return Restaurantero::select('id', 'nombre_restaurante')->orderBy('nombre_restaurante')->get();
    }

    /** @param array{restaurantero_id?: mixed, start?: ?string, end?: ?string, evento_id?: mixed} $filtros */
    public function eventos(array $filtros): Collection
    {
        $query = Cita::with(['restaurantero', 'cliente', 'servicio']);

        if (filled($filtros['restaurantero_id'] ?? null)) {
            $query->where('restaurantero_id', $filtros['restaurantero_id']);
        }

        if (filled($filtros['start'] ?? null)) {
            $query->where('inicio', '>=', $filtros['start']);
        }

        if (filled($filtros['end'] ?? null)) {
            $query->where('inicio', '<=', $filtros['end']);
        }

        // Solo la API manda evento_id.
        if (filled($filtros['evento_id'] ?? null)) {
            $query->where('edicion_id', $filtros['evento_id']);
        }

        return $query->get()->map(function (Cita $cita) {
            return [
                'id'    => $cita->id,
                'title' => ($cita->cliente->name ?? 'Cliente') . ' — ' . ($cita->servicio->nombre ?? 'Servicio'),
                'start' => $cita->inicio->toIso8601String(),
                'end'   => $cita->fin->toIso8601String(),
                'color' => self::COLORES[$cita->estado] ?? self::COLOR_POR_DEFECTO,
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
    }
}
