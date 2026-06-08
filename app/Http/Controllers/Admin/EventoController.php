<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Cita;
use App\Models\Evento;
use App\Models\Restaurantero;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class EventoController extends Controller
{
    public function index()
    {
        $pendientesPorEvento = DB::table('evento_usuario')
            ->where('estado', 'pendiente')
            ->selectRaw('evento_id, count(*) as total')
            ->groupBy('evento_id')
            ->pluck('total', 'evento_id');

        $eventos = Evento::withCount(['citas', 'restauranteros'])
            ->orderByDesc('created_at')
            ->get()
            ->map(function ($evento) use ($pendientesPorEvento) {
                $evento->usuarios_count = Cita::where('edicion_id', $evento->id)
                    ->distinct('cliente_id')
                    ->count('cliente_id');
                $evento->solicitudes_pendientes = $pendientesPorEvento[$evento->id] ?? 0;
                return $evento;
            });

        return Inertia::render('Admin/Eventos/Index', [
            'eventos'   => $eventos,
            'categorias' => Restaurantero::$categorias,
        ]);
    }

    private function validationRules(): array
    {
        return [
            'nombre'                          => 'required|string|max:200',
            'sector_economico'                => 'nullable|string|max:100',
            'descripcion'                     => 'nullable|string|max:1000',
            'fecha_hora_inicio'               => 'nullable|date',
            'fecha_hora_fin'                  => 'nullable|date|after_or_equal:fecha_hora_inicio',
            'fecha_hora_inicio_proveedores'   => 'nullable|date',
            'fecha_hora_fin_proveedores'      => 'nullable|date|after_or_equal:fecha_hora_inicio_proveedores',
            'fecha_hora_inicio_compradores'   => 'nullable|date|after_or_equal:fecha_hora_inicio_proveedores',
            'fecha_hora_fin_compradores'      => 'nullable|date|after_or_equal:fecha_hora_inicio_compradores',
            'max_citas_por_comprador'         => 'nullable|integer|min:1|max:50',
            'tiempo_entre_citas_minutos'      => [
                'nullable', 'integer', 'min:5', 'max:120',
                function ($attribute, $value, $fail) {
                    if ($value !== null && $value % 5 !== 0) {
                        $fail('El tiempo entre citas debe ser múltiplo de 5 (5, 10, 15, 20, 25, 30...).');
                    }
                },
            ],
        ];
    }

    private function eventoFields(Request $request): array
    {
        return [
            'nombre'                         => $request->nombre,
            'sector_economico'               => $request->sector_economico,
            'descripcion'                    => $request->descripcion,
            'fecha_hora_inicio'              => $request->fecha_hora_inicio,
            'fecha_hora_fin'                 => $request->fecha_hora_fin,
            'fecha_hora_inicio_proveedores'  => $request->fecha_hora_inicio_proveedores,
            'fecha_hora_fin_proveedores'     => $request->fecha_hora_fin_proveedores,
            'fecha_hora_inicio_compradores'  => $request->fecha_hora_inicio_compradores,
            'fecha_hora_fin_compradores'     => $request->fecha_hora_fin_compradores,
            'max_citas_por_comprador'        => $request->max_citas_por_comprador ?? 3,
            'tiempo_entre_citas_minutos'     => $request->tiempo_entre_citas_minutos ?? 30,
        ];
    }

    public function store(Request $request)
    {
        $request->validate($this->validationRules());

        Evento::create(array_merge($this->eventoFields($request), [
            'fecha_inicio' => $request->fecha_hora_inicio ?? now()->toDateString(),
            'activa'       => false,
        ]));

        return back()->with('success', 'Evento creado. Actívalo cuando estés listo.');
    }

    public function update(Request $request, Evento $evento)
    {
        $request->validate($this->validationRules());

        $evento->update($this->eventoFields($request));

        return back()->with('success', 'Evento actualizado correctamente.');
    }

    public function archivar(Evento $evento)
    {
        if (!$evento->activa) {
            return back()->withErrors(['error' => 'Este evento no está activo.']);
        }

        $evento->update([
            'activa'      => false,
            'fecha_corte' => now()->toDateString(),
        ]);

        return back()->with('success', 'Evento archivado correctamente. Ahora puedes activar uno nuevo.');
    }

    public function activar(Evento $evento)
    {
        Evento::query()->update(['activa' => false]);

        $evento->update([
            'activa'      => true,
            'fecha_corte' => null,
        ]);

        // Sync service duration for all approved providers in this event
        if ($evento->tiempo_entre_citas_minutos) {
            \App\Models\Servicio::whereIn('restaurantero_id',
                \App\Models\Restaurantero::whereExists(function ($q) use ($evento) {
                    $q->from('evento_usuario')
                        ->whereColumn('evento_usuario.user_id', 'restauranteros.user_id')
                        ->where('evento_usuario.evento_id', $evento->id)
                        ->where('evento_usuario.tipo', 'proveedor')
                        ->where('evento_usuario.estado', 'aprobado');
                })->pluck('id')
            )->update(['duracion_minutos' => $evento->tiempo_entre_citas_minutos]);
        }

        return back()->with('success', 'Evento "' . $evento->nombre . '" activado.');
    }

    public function destroy(Evento $evento)
    {
        if ($evento->activa) {
            return back()->withErrors(['error' => 'No puedes eliminar el evento activo. Archívalo primero.']);
        }

        if ($evento->citas()->count() > 0) {
            return back()->withErrors(['error' => 'No puedes eliminar un evento que tiene citas registradas.']);
        }

        $evento->delete();

        return back()->with('success', 'Evento eliminado correctamente.');
    }
}
