<?php

namespace App\Http\Controllers;

use App\Models\Evento;
use App\Models\Notificacion;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EventoRegistroController extends Controller
{
    public function registrarComprador(Request $request, Evento $evento)
    {
        $user = $request->user();

        if (!$user->hasRole('cliente')) {
            return back()->withErrors(['error' => 'Necesitas el rol de comprador para registrarte.']);
        }

        if ($evento->fecha_hora_fin && now()->gt($evento->fecha_hora_fin)) {
            return back()->withErrors(['error' => 'Este evento ya ha finalizado.']);
        }

        $registro = DB::table('evento_usuario')
            ->where('evento_id', $evento->id)
            ->where('user_id', $user->id)
            ->where('tipo', 'comprador')
            ->first();

        if ($registro) {
            if ($registro->estado === 'pendiente') {
                return back()->with('success', 'Tu solicitud ya está en revisión. Te avisaremos cuando sea aprobada.');
            }
            if ($registro->estado === 'aprobado') {
                return back()->with('success', 'Ya estás aprobado en este evento como comprador.');
            }
            // Si fue rechazado, se permite re-solicitar
            DB::table('evento_usuario')
                ->where('evento_id', $evento->id)
                ->where('user_id', $user->id)
                ->where('tipo', 'comprador')
                ->update(['estado' => 'pendiente', 'motivo_rechazo' => null, 'respondido_at' => null]);
        } else {
            DB::table('evento_usuario')->insert([
                'evento_id'  => $evento->id,
                'user_id'    => $user->id,
                'tipo'       => 'comprador',
                'estado'     => 'pendiente',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Notificar al admin
        $admin = User::role('admin')->first();
        if ($admin) {
            Notificacion::crear(
                $admin->id,
                'solicitud_registro',
                'Nueva solicitud de comprador',
                $user->name . ' solicita unirse al evento "' . $evento->nombre . '" como comprador.'
            );
        }

        return back()->with('success', 'Tu solicitud de registro fue enviada. El administrador la revisará pronto.');
    }

    public function registrarProveedor(Request $request, Evento $evento)
    {
        $user = $request->user();

        if (!$user->hasRole('restaurantero')) {
            return back()->withErrors(['error' => 'Necesitas el rol de proveedor para registrarte.']);
        }

        // Verificar que el restaurantero esté aprobado globalmente
        $restaurantero = $user->restaurantero;
        if (!$restaurantero || !$restaurantero->aprobado) {
            return back()->withErrors(['error' => 'Tu perfil de proveedor aún no ha sido aprobado por el administrador. Espera la aprobación antes de registrarte al evento.']);
        }

        if ($evento->fecha_hora_fin && now()->gt($evento->fecha_hora_fin)) {
            return back()->withErrors(['error' => 'Este evento ya ha finalizado.']);
        }

        $registro = DB::table('evento_usuario')
            ->where('evento_id', $evento->id)
            ->where('user_id', $user->id)
            ->where('tipo', 'proveedor')
            ->first();

        if ($registro) {
            if ($registro->estado === 'pendiente') {
                return back()->with('success', 'Tu solicitud ya está en revisión. Te avisaremos cuando sea aprobada.');
            }
            if ($registro->estado === 'aprobado') {
                return back()->with('success', 'Ya estás aprobado en este evento como proveedor.');
            }
            // Si fue rechazado, se permite re-solicitar
            DB::table('evento_usuario')
                ->where('evento_id', $evento->id)
                ->where('user_id', $user->id)
                ->where('tipo', 'proveedor')
                ->update(['estado' => 'pendiente', 'motivo_rechazo' => null, 'respondido_at' => null]);
        } else {
            DB::table('evento_usuario')->insert([
                'evento_id'  => $evento->id,
                'user_id'    => $user->id,
                'tipo'       => 'proveedor',
                'estado'     => 'pendiente',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Notificar al admin
        $admin = User::role('admin')->first();
        if ($admin) {
            Notificacion::crear(
                $admin->id,
                'solicitud_registro',
                'Nueva solicitud de proveedor',
                $user->name . ' solicita unirse al evento "' . $evento->nombre . '" como proveedor.'
            );
        }

        return back()->with('success', 'Tu solicitud fue enviada. El administrador la aprobará pronto.');
    }
}
