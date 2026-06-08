<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Evento;
use App\Models\Notificacion;
use App\Models\Restaurantero;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class EventoSolicitudesController extends Controller
{
    public function index(Evento $evento)
    {
        $registros = DB::table('evento_usuario')
            ->where('evento_id', $evento->id)
            ->get();

        $userIds = $registros->pluck('user_id')->unique();
        $users   = User::whereIn('id', $userIds)
            ->with('restaurantero')
            ->get()
            ->keyBy('id');

        $mapear = fn($reg) => [
            'id'             => $reg->id,
            'user_id'        => $reg->user_id,
            'tipo'           => $reg->tipo,
            'estado'         => $reg->estado,
            'motivo_rechazo' => $reg->motivo_rechazo,
            'respondido_at'  => $reg->respondido_at,
            'created_at'     => $reg->created_at,
            'user'           => isset($users[$reg->user_id]) ? [
                'id'       => $users[$reg->user_id]->id,
                'name'     => $users[$reg->user_id]->name,
                'email'    => $users[$reg->user_id]->email,
                'telefono' => $users[$reg->user_id]->telefono,
            ] : null,
            'restaurantero'  => ($reg->tipo === 'proveedor' && isset($users[$reg->user_id]))
                ? optional($users[$reg->user_id]->restaurantero)->only([
                    'id', 'nombre_restaurante', 'categoria', 'municipio', 'rfc', 'logo_path', 'aprobado',
                  ])
                : null,
        ];

        $pendientes = $registros->where('estado', 'pendiente')->values()->map($mapear)->values();
        $aprobados  = $registros->where('estado', 'aprobado')->values()->map($mapear)->values();
        $rechazados = $registros->where('estado', 'rechazado')->values()->map($mapear)->values();

        return Inertia::render('Admin/Eventos/Solicitudes', [
            'evento'     => $evento->only(['id', 'nombre', 'activa']),
            'pendientes' => $pendientes,
            'aprobados'  => $aprobados,
            'rechazados' => $rechazados,
        ]);
    }

    public function aprobar(Evento $evento, User $user, Request $request)
    {
        $registro = DB::table('evento_usuario')
            ->where('evento_id', $evento->id)
            ->where('user_id', $user->id)
            ->first();

        if (!$registro) {
            return back()->withErrors(['error' => 'Solicitud no encontrada.']);
        }

        DB::table('evento_usuario')
            ->where('evento_id', $evento->id)
            ->where('user_id', $user->id)
            ->update(['estado' => 'aprobado', 'respondido_at' => now()]);

        // Si es proveedor y tiene restaurantero, vincularlo al evento
        if ($registro->tipo === 'proveedor') {
            $restaurantero = $user->restaurantero;
            if ($restaurantero) {
                $restaurantero->update(['edicion_id' => $evento->id]);
            }
            $mensaje = '¡Fuiste aprobado como proveedor en el evento "' . $evento->nombre . '"! Ya apareces en el listado de proveedores.';
        } else {
            $mensaje = '¡Fuiste aprobado en el evento "' . $evento->nombre . '"! Ya puedes agendar citas con los proveedores.';
        }

        Notificacion::crear($user->id, 'solicitud_aprobada', 'Solicitud aprobada', $mensaje);

        return back()->with('success', 'Solicitud aprobada.');
    }

    public function rechazar(Evento $evento, User $user, Request $request)
    {
        $request->validate([
            'motivo_rechazo' => 'required|string|max:500',
        ]);

        $registro = DB::table('evento_usuario')
            ->where('evento_id', $evento->id)
            ->where('user_id', $user->id)
            ->first();

        if (!$registro) {
            return back()->withErrors(['error' => 'Solicitud no encontrada.']);
        }

        DB::table('evento_usuario')
            ->where('evento_id', $evento->id)
            ->where('user_id', $user->id)
            ->update([
                'estado'         => 'rechazado',
                'motivo_rechazo' => $request->motivo_rechazo,
                'respondido_at'  => now(),
            ]);

        $tipoTexto = $registro->tipo === 'proveedor' ? 'como proveedor ' : '';
        Notificacion::crear(
            $user->id,
            'solicitud_rechazada',
            'Solicitud rechazada',
            'Tu solicitud ' . $tipoTexto . 'para el evento "' . $evento->nombre . '" fue rechazada. Motivo: ' . $request->motivo_rechazo
        );

        return back()->with('success', 'Solicitud rechazada.');
    }

    public function aprobarTodos(Evento $evento, Request $request)
    {
        $request->validate(['tipo' => 'required|in:comprador,proveedor']);

        $pendientes = DB::table('evento_usuario')
            ->where('evento_id', $evento->id)
            ->where('tipo', $request->tipo)
            ->where('estado', 'pendiente')
            ->get();

        if ($pendientes->isEmpty()) {
            return back()->with('success', 'No hay solicitudes pendientes de ese tipo.');
        }

        DB::table('evento_usuario')
            ->where('evento_id', $evento->id)
            ->where('tipo', $request->tipo)
            ->where('estado', 'pendiente')
            ->update(['estado' => 'aprobado', 'respondido_at' => now()]);

        $esProveedor = $request->tipo === 'proveedor';

        foreach ($pendientes as $reg) {
            $user = User::find($reg->user_id);
            if (!$user) continue;

            if ($esProveedor) {
                $restaurantero = $user->restaurantero;
                if ($restaurantero) {
                    $restaurantero->update(['edicion_id' => $evento->id]);
                }
                $mensaje = '¡Fuiste aprobado como proveedor en el evento "' . $evento->nombre . '"! Ya apareces en el listado.';
            } else {
                $mensaje = '¡Fuiste aprobado en el evento "' . $evento->nombre . '"! Ya puedes agendar citas.';
            }

            Notificacion::crear($user->id, 'solicitud_aprobada', 'Solicitud aprobada', $mensaje);
        }

        $total = $pendientes->count();
        return back()->with('success', "{$total} solicitudes aprobadas.");
    }

    public function revertirPendiente(Evento $evento, User $user)
    {
        DB::table('evento_usuario')
            ->where('evento_id', $evento->id)
            ->where('user_id', $user->id)
            ->where('estado', 'rechazado')
            ->update(['estado' => 'pendiente', 'motivo_rechazo' => null, 'respondido_at' => null]);

        return back()->with('success', 'Solicitud regresada a pendiente.');
    }
}
