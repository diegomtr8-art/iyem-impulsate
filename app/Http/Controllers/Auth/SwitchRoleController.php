<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SwitchRoleController extends Controller
{
    public function __invoke(Request $request)
    {
        $user = $request->user();

        if (!$user->tieneDualRol()) {
            return back()->withErrors(['role' => 'No tienes habilitado el modo dual.']);
        }

        $nuevoRol = $user->active_role === 'comprador' ? 'proveedor' : 'comprador';

        // Verificar que no haya cita activa en la próxima hora (Feature 4 ampliará esto)
        $ahora = now();
        $enUnaHora = now()->addHour();

        $citaComoCliente = $user->citasComoCliente()
            ->whereIn('estado', ['pendiente', 'confirmada'])
            ->where('inicio', '>=', $ahora)
            ->where('inicio', '<=', $enUnaHora)
            ->first();

        if ($citaComoCliente) {
            $minutos = (int) $ahora->diffInMinutes($citaComoCliente->inicio);
            return back()->withErrors([
                'role' => "Tienes una cita en {$minutos} minutos. No puedes cambiar de perfil ahora.",
            ]);
        }

        // Verificar citas como proveedor
        $restaurantero = $user->restaurantero;
        if ($restaurantero) {
            $citaComoProveedor = \App\Models\Cita::where('restaurantero_id', $restaurantero->id)
                ->whereIn('estado', ['pendiente', 'confirmada'])
                ->where('inicio', '>=', $ahora)
                ->where('inicio', '<=', $enUnaHora)
                ->first();

            if ($citaComoProveedor) {
                $minutos = (int) $ahora->diffInMinutes($citaComoProveedor->inicio);
                return back()->withErrors([
                    'role' => "Tienes una cita como proveedor en {$minutos} minutos. No puedes cambiar de perfil ahora.",
                ]);
            }
        }

        $user->update(['active_role' => $nuevoRol]);

        $request->session()->save();
        return Inertia::location(route('dashboard'));
    }
}
