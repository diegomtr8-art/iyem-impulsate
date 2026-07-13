<?php

namespace App\Http\Controllers;

use App\Models\Evento;
use App\Models\Notificacion;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EventoRegistroController extends Controller
{
    public function registrarComprador(Request $request, Evento $evento)
    {
        $user = $request->user();

        if (!$user->hasRole('cliente')) {
            throw ValidationException::withMessages(['error' => 'Necesitas el rol de comprador para registrarte.']);
        }

        if (!$user->es_restaurantero) {
            throw ValidationException::withMessages(['error' => 'Solo los restauranteros pueden registrarse a este evento. Marca "¿Eres restaurantero?" en tu perfil para participar.']);
        }

        if ($evento->fecha_hora_fin && now()->gt($evento->fecha_hora_fin)) {
            throw ValidationException::withMessages(['error' => 'Este evento ya ha finalizado.']);
        }

        $finCompradores = $evento->fecha_hora_fin_compradores ?? $evento->fecha_hora_fin;
        if ($finCompradores && now()->gt($finCompradores)) {
            throw ValidationException::withMessages(['error' => 'El período de registro de compradores ya cerró.']);
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
            throw ValidationException::withMessages(['error' => 'Necesitas el rol de proveedor para registrarte.']);
        }

        $restaurantero = $user->restaurantero;
        if (!$restaurantero) {
            throw ValidationException::withMessages(['error' => 'Primero debes completar tu perfil de proveedor.']);
        }

        if ($evento->fecha_hora_fin && now()->gt($evento->fecha_hora_fin)) {
            throw ValidationException::withMessages(['error' => 'Este evento ya ha finalizado.']);
        }

        if ($evento->fecha_hora_fin_proveedores && now()->gt($evento->fecha_hora_fin_proveedores)) {
            throw ValidationException::withMessages(['error' =>
                'El período de registro de proveedores ya cerró el ' .
                $evento->fecha_hora_fin_proveedores->format('d/m/Y H:i') . '.']);
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

        $admin = User::role('admin')->first();
        if ($admin) {
            Notificacion::crear(
                $admin->id,
                'solicitud_registro',
                'Nueva solicitud de proveedor',
                $user->name . ' solicita unirse al evento "' . $evento->nombre . '" como proveedor.'
            );
        }

        return back()->with('success', 'Tu solicitud de registro como proveedor fue enviada. El administrador la aprobará pronto.');
    }
}
