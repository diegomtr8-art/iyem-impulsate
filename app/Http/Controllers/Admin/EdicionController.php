<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Cita;
use App\Models\Edicion;
use Illuminate\Http\Request;
use Inertia\Inertia;

class EdicionController extends Controller
{
    public function index()
    {
        $ediciones = Edicion::withCount(['citas', 'restauranteros'])
            ->orderByDesc('created_at')
            ->get()
            ->map(function ($edicion) {
                $edicion->usuarios_count = Cita::where('edicion_id', $edicion->id)
                    ->distinct('cliente_id')
                    ->count('cliente_id');
                return $edicion;
            });

        return Inertia::render('Admin/Ediciones/Index', [
            'ediciones' => $ediciones,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'nombre'              => 'required|string|max:200',
            'sector'              => 'nullable|string|max:100',
            'descripcion'         => 'nullable|string|max:1000',
            'fecha_inicio'        => 'required|date',
            'fecha_inicio_agenda' => 'nullable|date',
            'fecha_fin_agenda'    => 'nullable|date|after_or_equal:fecha_inicio_agenda',
        ]);

        Edicion::create([
            'nombre'              => $request->nombre,
            'sector'              => $request->sector,
            'descripcion'         => $request->descripcion,
            'fecha_inicio'        => $request->fecha_inicio,
            'fecha_inicio_agenda' => $request->fecha_inicio_agenda,
            'fecha_fin_agenda'    => $request->fecha_fin_agenda,
            'activa'              => false,
        ]);

        return back()->with('success', 'Edición creada. Actívala cuando estés listo.');
    }

    public function archivar(Edicion $edicion)
    {
        if (!$edicion->activa) {
            return back()->withErrors(['error' => 'Esta edición no está activa.']);
        }

        $edicion->update([
            'activa'      => false,
            'fecha_corte' => now()->toDateString(),
        ]);

        return back()->with('success', 'Edición archivada correctamente. Ahora puedes activar una nueva.');
    }

    public function activar(Edicion $edicion)
    {
        // Desactivar todas las ediciones
        Edicion::query()->update(['activa' => false]);

        // Activar la seleccionada (quitar fecha_corte si la tenía)
        $edicion->update([
            'activa'      => true,
            'fecha_corte' => null,
        ]);

        return back()->with('success', 'Edición "' . $edicion->nombre . '" activada.');
    }

    public function destroy(Edicion $edicion)
    {
        if ($edicion->activa) {
            return back()->withErrors(['error' => 'No puedes eliminar la edición activa. Archívala primero.']);
        }

        if ($edicion->citas()->count() > 0) {
            return back()->withErrors(['error' => 'No puedes eliminar una edición que tiene citas registradas.']);
        }

        $edicion->delete();

        return back()->with('success', 'Edición eliminada correctamente.');
    }
}
