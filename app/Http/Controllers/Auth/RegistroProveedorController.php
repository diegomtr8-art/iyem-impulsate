<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Edicion;
use App\Models\Horario;
use App\Models\Restaurantero;
use App\Models\Servicio;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;

class RegistroProveedorController extends Controller
{
    public function create()
    {
        return Inertia::render('Auth/RegisterProveedor');
    }

    public function store(Request $request)
    {
        // Si el email ya existe: asignar rol proveedor al usuario existente
        $existing = User::where('email', $request->email)->first();
        if ($existing) {
            if (!$existing->hasRole('restaurantero')) {
                $existing->assignRole('restaurantero');
            }
            // Cambiar a modo proveedor
            $existing->update(['active_role' => 'proveedor']);

            // Crear perfil stub si no existe
            $existing->refresh();
            if (!$existing->restaurantero) {
                $r = Restaurantero::create([
                    'user_id'                  => $existing->id,
                    'edicion_id'               => Edicion::activa()?->id,
                    'nombre_restaurante'       => $existing->name . ' — Negocio',
                    'activo'                   => false,
                    'aprobado'                 => false,
                    'solicitado_aprobacion_at' => now(),
                ]);
                \App\Models\Evento::registrarProveedorEnEventoActivo($existing->id);
                $this->crearServicioYHorarios($r);
            } else {
                $this->crearServicioYHorarios($existing->restaurantero);
            }
            Auth::login($existing);
            return redirect()->route('restaurantero.panel')
                ->with('success', 'Cuenta actualizada como Proveedor. Completa tu perfil para aparecer en el directorio.');
        }

        $request->validate([
            'name'     => ['required', 'string', 'max:255'],
            'email'    => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'telefono' => ['nullable', 'string', 'max:20'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $user = User::create([
            'name'        => $request->name,
            'email'       => $request->email,
            'password'    => Hash::make($request->password),
            'telefono'    => $request->telefono,
            'active_role' => 'proveedor',
        ]);

        $user->assignRole('restaurantero');

        // Crear perfil stub para que el admin lo vea inmediatamente
        $restaurantero = Restaurantero::create([
            'user_id'                  => $user->id,
            'edicion_id'               => Edicion::activa()?->id,
            'nombre_restaurante'       => $user->name . ' — Negocio',
            'activo'                   => false,
            'aprobado'                 => false,
            'solicitado_aprobacion_at' => now(),
        ]);

        \App\Models\Evento::registrarProveedorEnEventoActivo($user->id);
        $this->crearServicioYHorarios($restaurantero);

        Auth::login($user);

        return redirect()->route('dashboard');
    }

    private function crearServicioYHorarios(Restaurantero $r): void
    {
        if (!$r->servicios()->exists()) {
            Servicio::create([
                'restaurantero_id' => $r->id,
                'nombre'           => 'Mesa de Networking',
                'duracion_minutos' => 30,
                'precio'           => 0,
                'activo'           => true,
            ]);
        }
        if (!$r->horarios()->exists()) {
            for ($dia = 1; $dia <= 5; $dia++) {
                Horario::create([
                    'restaurantero_id' => $r->id,
                    'dia_semana'       => $dia,
                    'hora_inicio'      => '09:00:00',
                    'hora_fin'         => '16:00:00',
                    'activo'           => true,
                ]);
            }
        }
    }
}
