<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Cita;
use App\Services\Admin\CalendarioAdminService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Calendario general de citas (web: /admin/calendario, feed de FullCalendar).
 * Misma forma de cada entrada que la web (title, start, end, color, extendedProps).
 */
class CalendarioController extends Controller
{
    /** Rango maximo por consulta: sin limite la web puede traer TODAS las citas. */
    public const MAX_DIAS = 62;

    public function __construct(private CalendarioAdminService $calendario) {}

    public function index(Request $request)
    {
        Gate::authorize('administrar', Cita::class);
        $f = $request->validate([
            'start'            => ['required', 'date'],
            'end'              => ['required', 'date', 'after_or_equal:start'],
            'restaurantero_id' => ['nullable', 'integer'],
            'evento_id'        => ['nullable', 'integer'],
        ]);

        if (\Carbon\Carbon::parse($f['start'])->diffInDays(\Carbon\Carbon::parse($f['end'])) > self::MAX_DIAS) {
            throw new \App\Exceptions\ReglaDeNegocioException('El rango no puede pasar de ' . self::MAX_DIAS . ' días.', 'end');
        }

        $eventos = $this->calendario->eventos($f);

        return response()->json(['data' => $eventos->values(), 'meta' => ['total' => $eventos->count()]]);
    }

    public function proveedores()
    {
        Gate::authorize('administrar', Cita::class);

        return response()->json(['data' => $this->calendario->proveedores()->map(fn ($r) => [
            'id' => $r->id, 'nombre_negocio' => $r->nombre_restaurante,
        ])->values()]);
    }
}
