<?php

namespace App\Http\Controllers;

use App\Models\Evento;
use App\Models\Horario;
use App\Models\Restaurantero;
use App\Models\Servicio;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
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
            'telefono'              => ['required', 'string', 'max:20'],
            'curp'                  => ['nullable', 'string', 'max:18'],
            'rfc'                   => ['nullable', 'string', 'max:13'],
            'municipio'             => ['nullable', 'string', 'max:100'],
            'nombre_empresa'        => ['required', 'string', 'max:200'],
            'camara_asociacion'     => ['required', 'string', 'in:CANIRAC,Ninguna'],
            'nombre_establecimiento'=> [
                'nullable', 'string', 'max:255',
                Rule::requiredIf($request->camara_asociacion === 'CANIRAC'),
            ],
        ]);

        $request->user()->update([
            'telefono'               => $request->telefono,
            'curp'                   => $request->curp ? strtoupper($request->curp) : null,
            'rfc'                    => $request->rfc ? strtoupper($request->rfc) : null,
            'municipio'              => $request->municipio,
            'nombre_empresa'         => $request->nombre_empresa,
            'perfil_completo'        => true,
            'camara_asociacion'      => $request->camara_asociacion,
            'nombre_establecimiento' => $request->camara_asociacion === 'CANIRAC'
                                        ? $request->nombre_establecimiento
                                        : null,
        ]);

        return redirect()->route('dashboard')
            ->with('success', '¡Perfil completado! Ya puedes acceder a todas las funciones.');
    }

    public function actualizarComprador(Request $request)
    {
        $request->validate([
            'name'                  => ['required', 'string', 'max:255'],
            'telefono'              => ['nullable', 'string', 'max:20'],
            'curp'                  => ['nullable', 'string', 'max:18'],
            'rfc'                   => ['nullable', 'string', 'max:13'],
            'municipio'             => ['nullable', 'string', 'max:100'],
            'nombre_empresa'        => ['required', 'string', 'max:200'],
            'sitio_web'             => ['nullable', 'url', 'max:200'],
            'necesidades'           => ['nullable', 'string', 'max:2000'],
            'camara_asociacion'     => ['required', 'string', 'in:CANIRAC,Ninguna'],
            'nombre_establecimiento'=> [
                'nullable', 'string', 'max:255',
                Rule::requiredIf($request->camara_asociacion === 'CANIRAC'),
            ],
        ]);

        $request->user()->update([
            'name'                   => $request->name,
            'telefono'               => $request->telefono,
            'curp'                   => $request->curp ? strtoupper($request->curp) : null,
            'rfc'                    => $request->rfc ? strtoupper($request->rfc) : null,
            'municipio'              => $request->municipio,
            'nombre_empresa'         => $request->nombre_empresa,
            'sitio_web'              => $request->sitio_web,
            'necesidades'            => $request->necesidades,
            'camara_asociacion'      => $request->camara_asociacion,
            'nombre_establecimiento' => $request->camara_asociacion === 'CANIRAC'
                                        ? $request->nombre_establecimiento
                                        : null,
        ]);

        return back()->with('success', 'Perfil actualizado correctamente.');
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

    public function agregarRol(Request $request)
    {
        $request->validate(['rol' => 'required|in:comprador,proveedor']);

        $user = $request->user();

        if ($request->rol === 'proveedor') {
            if (!$user->hasRole('restaurantero')) {
                $user->assignRole('restaurantero');
            }
            if (!$user->restaurantero) {
                $restaurantero = Restaurantero::create([
                    'user_id'                  => $user->id,
                    'edicion_id'               => Evento::activo()?->id,
                    'nombre_restaurante'       => $user->name . ' — Negocio',
                    'activo'                   => false,
                    'aprobado'                 => false,
                    'solicitado_aprobacion_at' => now(),
                ]);
                // NO auto-registrar: el proveedor elige el evento desde su dashboard.
                if (!$restaurantero->servicios()->exists()) {
                    Servicio::create([
                        'restaurantero_id' => $restaurantero->id,
                        'nombre'           => 'Mesa de Networking',
                        'duracion_minutos' => 30,
                        'precio'           => 0,
                        'activo'           => true,
                    ]);
                }
                for ($dia = 1; $dia <= 5; $dia++) {
                    Horario::create([
                        'restaurantero_id' => $restaurantero->id,
                        'dia_semana'       => $dia,
                        'hora_inicio'      => '09:00:00',
                        'hora_fin'         => '16:00:00',
                        'activo'           => true,
                    ]);
                }
            }
        } else {
            if (!$user->hasRole('cliente')) {
                $user->assignRole('cliente');
            }
        }

        return back()->with('success', 'Rol agregado correctamente. ¡Bienvenido!');
    }
}
