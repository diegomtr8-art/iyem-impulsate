<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Evento;
use App\Models\Horario;
use App\Models\Restaurantero;
use App\Models\Servicio;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SeleccionarRolController extends Controller
{
    public function create()
    {
        return Inertia::render('Auth/SeleccionarRol');
    }

    public function store(Request $request)
    {
        $request->validate([
            'rol' => 'required|in:comprador,proveedor,ambos',
        ]);

        $user = $request->user();
        $rol  = $request->rol;

        if ($rol === 'comprador') {
            if (!$user->hasRole('cliente')) {
                $user->assignRole('cliente');
            }
            $user->update(['active_role' => 'comprador', 'rol_seleccionado' => true]);

        } elseif ($rol === 'proveedor') {
            if (!$user->hasRole('restaurantero')) {
                $user->assignRole('restaurantero');
            }
            $user->update(['active_role' => 'proveedor', 'rol_seleccionado' => true]);
            $this->crearPerfilProveedor($user);

        } elseif ($rol === 'ambos') {
            if (!$user->hasRole('cliente')) {
                $user->assignRole('cliente');
            }
            if (!$user->hasRole('restaurantero')) {
                $user->assignRole('restaurantero');
            }
            $user->update(['active_role' => 'comprador', 'rol_seleccionado' => true]);
            $this->crearPerfilProveedor($user);
        }

        return redirect()->route('dashboard')->with('success', '¡Bienvenido a Impulsate!');
    }

    private function crearPerfilProveedor($user): void
    {
        if ($user->restaurantero) {
            return;
        }

        $restaurantero = Restaurantero::create([
            'user_id'                  => $user->id,
            'edicion_id'               => Evento::activo()?->id,
            'nombre_restaurante'       => $user->name . ' — Negocio',
            'activo'                   => false,
            'aprobado'                 => false,
            'solicitado_aprobacion_at' => now(),
        ]);

        // NO auto-registrar al evento: el proveedor debe elegirlo
        // desde la pestaña Eventos de su dashboard (con el modal de convocatoria).

        if (!$restaurantero->servicios()->exists()) {
            Servicio::create([
                'restaurantero_id' => $restaurantero->id,
                'nombre'           => 'Mesa de Networking',
                'duracion_minutos' => 30,
                'precio'           => 0,
                'activo'           => true,
            ]);
        }

        if (!$restaurantero->horarios()->exists()) {
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
    }
}
