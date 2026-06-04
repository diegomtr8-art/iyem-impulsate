<?php

namespace App\Http\Controllers;

use App\Models\Restaurantero;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CompletarPerfilController extends Controller
{
    public function create(Request $request)
    {
        return Inertia::render('Auth/CompletarPerfil', [
            'user' => $request->user(),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'telefono' => ['required', 'string', 'max:20'],
            'curp'     => ['nullable', 'string', 'size:18'],
        ]);

        $request->user()->update([
            'telefono'        => $request->telefono,
            'curp'            => $request->curp,
            'perfil_completo' => true,
        ]);

        return redirect()->route('dashboard')
            ->with('success', '¡Perfil completado! Ya puedes acceder a todas las funciones.');
    }

    /** Guardar "necesidades" del comprador y retornar sugerencias */
    public function necesidades(Request $request)
    {
        $request->validate([
            'necesidades' => ['required', 'string', 'max:2000'],
        ]);

        $request->user()->update(['necesidades' => $request->necesidades]);

        // Buscar proveedores sugeridos por palabras clave
        $palabras = collect(preg_split('/\s+/', $request->necesidades))
            ->filter(fn($p) => strlen($p) >= 4)
            ->take(10);

        $query = Restaurantero::where('aprobado', true)->where('activo', true);

        foreach ($palabras as $palabra) {
            $query->orWhere('descripcion', 'LIKE', "%{$palabra}%")
                  ->orWhere('nombre_restaurante', 'LIKE', "%{$palabra}%")
                  ->orWhere('categoria', 'LIKE', "%{$palabra}%");
        }

        $sugeridos = $query->take(5)->get(['id', 'nombre_restaurante', 'categoria', 'descripcion', 'foto_path', 'logo_path', 'municipio']);

        return response()->json([
            'sugeridos' => $sugeridos,
        ]);
    }
}
