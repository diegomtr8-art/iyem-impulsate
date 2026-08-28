<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\EventoSolicitudAprobadaMail;
use App\Mail\EventoSolicitudRechazadaMail;
use App\Models\Evento;
use App\Models\Notificacion;
use App\Models\Restaurantero;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
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

        $citasHistoricoPorUser = DB::table('citas')
            ->whereIn('cliente_id', $userIds)
            ->selectRaw('cliente_id, count(*) as total')
            ->groupBy('cliente_id')
            ->pluck('total', 'cliente_id');

        $mapear = fn($reg) => [
            'id'             => $reg->id,
            'user_id'        => $reg->user_id,
            'tipo'           => $reg->tipo,
            'estado'         => $reg->estado,
            'motivo_rechazo' => $reg->motivo_rechazo,
            'respondido_at'  => $reg->respondido_at,
            'created_at'     => $reg->created_at,
            'user'           => isset($users[$reg->user_id]) ? [
                'id'                => $users[$reg->user_id]->id,
                'name'              => $users[$reg->user_id]->name,
                'email'             => $users[$reg->user_id]->email,
                'telefono'          => $users[$reg->user_id]->telefono,
                'sitio_web'         => $users[$reg->user_id]->sitio_web,
                'necesidades'       => $users[$reg->user_id]->necesidades,
                'empresa_nombre'    => $users[$reg->user_id]->empresa_nombre ?? null,
                'rfc'               => $users[$reg->user_id]->rfc ?? null,
                'nombre_empresa'    => $users[$reg->user_id]->nombre_empresa ?? null,
                'camara_asociacion' => $users[$reg->user_id]->camara_asociacion ?? null,
                'nombre_establecimiento' => $users[$reg->user_id]->nombre_establecimiento ?? null,
                'created_at'        => $users[$reg->user_id]->created_at,
                'citas_historico'   => $citasHistoricoPorUser[$reg->user_id] ?? 0,
                // Booleanos en vez de rutas: el frontend solo los usa como
                // bandera v-if, y los enlaces reales van por route('documentos.ver').
                // No hay razon para exponer rutas internas del filesystem.
                'tiene_ine' => !empty($users[$reg->user_id]->ine_path),
                'tiene_csf' => !empty($users[$reg->user_id]->csf_path),
                'csf_fecha' => $users[$reg->user_id]->csf_fecha ?? null,
            ] : null,
            'restaurantero'  => ($reg->tipo === 'proveedor' && isset($users[$reg->user_id]))
                ? (function () use ($users, $reg) {
                    $r = $users[$reg->user_id]->restaurantero;
                    if (!$r) return null;
                    return [
                        'id'                     => $r->id,
                        'nombre_restaurante'     => $r->nombre_restaurante,
                        'descripcion'            => $r->descripcion,
                        'categoria'              => $r->categoria,
                        'municipio'              => $r->municipio,
                        'rfc'                    => $r->rfc,
                        'domicilio_en_yucatan'   => $r->domicilio_en_yucatan,
                        'telefono'               => $r->telefono,
                        'sitio_web'              => $r->sitio_web,
                        'redes_sociales'         => $r->redes_sociales,
                        'productos_top'          => $r->productos_top,
                        'logo_path'              => $r->logo_path,
                        'foto_path'              => $r->foto_path,
                        'activo'                 => $r->activo,
                        'aprobado'               => $r->aprobado,
                        'perfil_completo'        => $r->perfil_completo,
                        'acepta_credito'         => $r->acepta_credito,
                        'credito_monto_maximo'   => $r->credito_monto_maximo,
                        'credito_tiempo_cantidad'=> $r->credito_tiempo_cantidad,
                        'credito_tiempo_unidad'  => $r->credito_tiempo_unidad,
                        'pago_contraentrega'     => $r->pago_contraentrega,
                        'factura'                => $r->factura,
                        'regimen_fiscal'         => $r->regimen_fiscal,
                        'entrega_domicilio'      => $r->entrega_domicilio,
                        'cobertura_entrega'      => $r->cobertura_entrega,
                        'forma_entrega'          => $r->forma_entrega,
                        'created_at'             => $r->created_at,
                        'citas_count'            => \App\Models\Cita::where('restaurantero_id', $r->id)->count(),
                    ];
                })()
                : null,
            'citas_activas'  => (function () use ($users, $reg) {
                if (!isset($users[$reg->user_id])) return 0;
                $u = $users[$reg->user_id];
                if ($reg->tipo === 'proveedor') {
                    $r = $u->restaurantero;
                    if (!$r) return 0;
                    return \App\Models\Cita::where('restaurantero_id', $r->id)
                        ->whereNotIn('estado', ['cancelada', 'rechazada'])
                        ->count();
                }
                return \App\Models\Cita::where('cliente_id', $u->id)
                    ->whereNotIn('estado', ['cancelada', 'rechazada'])
                    ->count();
            })(),
        ];

        $pendientes = $registros->where('estado', 'pendiente')->values()->map($mapear)->values();
        $aprobados  = $registros->where('estado', 'aprobado')->values()->map($mapear)->values();
        $rechazados = $registros->where('estado', 'rechazado')->values()->map($mapear)->values();

        // Proveedores/compradores que aún no tienen ningún registro (de cualquier estado) en este evento,
        // para el formulario de alta manual.
        $userIdsConRegistroProveedor = $registros->where('tipo', 'proveedor')->pluck('user_id');
        $userIdsConRegistroComprador = $registros->where('tipo', 'comprador')->pluck('user_id');

        $todosProveedores = Restaurantero::whereNotIn('user_id', $userIdsConRegistroProveedor)
            ->orderBy('nombre_restaurante')
            ->get(['id', 'nombre_restaurante', 'user_id']);

        $todosCompradores = User::whereDoesntHave('roles', fn ($q) => $q->where('name', 'admin'))
            ->whereNotIn('id', $userIdsConRegistroComprador)
            ->orderBy('name')
            ->get(['id', 'name', 'nombre_empresa']);

        return Inertia::render('Admin/Eventos/Solicitudes', [
            'evento'           => $evento->only(['id', 'nombre', 'activa', 'tipo_evento']),
            'pendientes'       => $pendientes,
            'aprobados'        => $aprobados,
            'rechazados'       => $rechazados,
            'todosProveedores' => $todosProveedores,
            'todosCompradores' => $todosCompradores,
        ]);
    }

    public function aprobar(Evento $evento, User $user, Request $request)
    {
        $request->validate([
            'tipo' => 'required|in:proveedor,comprador,expositor',
        ]);

        $tipo = $request->tipo;

        $registro = DB::table('evento_usuario')
            ->where('evento_id', $evento->id)
            ->where('user_id',   $user->id)
            ->where('tipo',      $tipo)
            ->first();

        if (!$registro) {
            return back()->withErrors(['error' => 'Solicitud no encontrada.']);
        }

        DB::table('evento_usuario')
            ->where('evento_id', $evento->id)
            ->where('user_id',   $user->id)
            ->where('tipo',      $tipo)
            ->update(['estado' => 'aprobado', 'respondido_at' => now()]);

        // Si es proveedor y tiene restaurantero, vincularlo al evento
        if ($tipo === 'proveedor') {
            $restaurantero = $user->restaurantero;
            if ($restaurantero) {
                $restaurantero->update(['edicion_id' => $evento->id]);
            }
            $mensaje = '¡Fuiste aprobado como proveedor en el evento "' . $evento->nombre . '"! Ya apareces en el listado de proveedores.';
        } elseif ($tipo === 'expositor') {
            $mensaje = '¡Fuiste aprobado como expositor en el bazar "' . $evento->nombre . '"! Recibirás más información sobre tu espacio.';
        } else {
            $mensaje = '¡Fuiste aprobado en el evento "' . $evento->nombre . '"! Ya puedes agendar citas con los proveedores.';
        }

        Notificacion::crear($user->id, 'solicitud_aprobada', 'Solicitud aprobada', $mensaje);

        try {
            Mail::to($user->email)->send(
                new EventoSolicitudAprobadaMail($user, $evento, $tipo)
            );
        } catch (\Exception $e) {
            \Log::warning('Error enviando correo de aprobación de evento: ' . $e->getMessage());
        }

        return back()->with('success', 'Solicitud aprobada.');
    }

    public function rechazar(Evento $evento, User $user, Request $request)
    {
        $request->validate([
            'tipo'           => 'required|in:proveedor,comprador,expositor',
            'motivo_rechazo' => 'required|string|max:500',
        ]);

        $tipo = $request->tipo;

        $registro = DB::table('evento_usuario')
            ->where('evento_id', $evento->id)
            ->where('user_id',   $user->id)
            ->where('tipo',      $tipo)
            ->first();

        if (!$registro) {
            return back()->withErrors(['error' => 'Solicitud no encontrada.']);
        }

        DB::table('evento_usuario')
            ->where('evento_id', $evento->id)
            ->where('user_id',   $user->id)
            ->where('tipo',      $tipo)
            ->update([
                'estado'         => 'rechazado',
                'motivo_rechazo' => $request->motivo_rechazo,
                'respondido_at'  => now(),
            ]);

        $tipoTexto = $tipo === 'proveedor' ? 'como proveedor ' : '';
        Notificacion::crear(
            $user->id,
            'solicitud_rechazada',
            'Solicitud rechazada',
            'Tu solicitud ' . $tipoTexto . 'para el evento "' . $evento->nombre . '" fue rechazada. Motivo: ' . $request->motivo_rechazo
        );

        try {
            Mail::to($user->email)->send(
                new EventoSolicitudRechazadaMail(
                    $user,
                    $evento,
                    $tipo,
                    $request->motivo_rechazo
                )
            );
        } catch (\Exception $e) {
            \Log::warning('Error enviando correo de rechazo de evento: ' . $e->getMessage());
        }

        return back()->with('success', 'Solicitud rechazada.');
    }

    public function aprobarTodos(Evento $evento, Request $request)
    {
        $request->validate(['tipo' => 'required|in:comprador,proveedor,expositor']);

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

            try {
                Mail::to($user->email)->send(
                    new EventoSolicitudAprobadaMail($user, $evento, $reg->tipo)
                );
            } catch (\Exception $e) {
                \Log::warning('Correo aprobación masiva fallido para user ' . $user->id . ': ' . $e->getMessage());
            }
        }

        $total = $pendientes->count();
        return back()->with('success', "{$total} solicitudes aprobadas.");
    }

    public function revertirPendiente(Evento $evento, User $user, Request $request)
    {
        $request->validate([
            'tipo' => 'required|in:proveedor,comprador,expositor',
        ]);

        DB::table('evento_usuario')
            ->where('evento_id', $evento->id)
            ->where('user_id',   $user->id)
            ->where('tipo',      $request->tipo)
            ->where('estado',    'rechazado')
            ->update(['estado' => 'pendiente', 'motivo_rechazo' => null, 'respondido_at' => null]);

        return back()->with('success', 'Solicitud regresada a pendiente.');
    }

    public function agregarProveedor(Evento $evento, Request $request)
    {
        $request->validate([
            'restaurantero_id' => 'required|exists:restauranteros,id',
        ]);

        $restaurantero = Restaurantero::findOrFail($request->restaurantero_id);

        $yaRegistrado = DB::table('evento_usuario')
            ->where('evento_id', $evento->id)
            ->where('user_id',   $restaurantero->user_id)
            ->where('tipo',      'proveedor')
            ->exists();

        if ($yaRegistrado) {
            return back()->withErrors(['error' => 'Este proveedor ya está registrado en el evento.']);
        }

        DB::table('evento_usuario')->insert([
            'evento_id'     => $evento->id,
            'user_id'       => $restaurantero->user_id,
            'tipo'          => 'proveedor',
            'estado'        => 'aprobado',
            'respondido_at' => now(),
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        $restaurantero->update(['edicion_id' => $evento->id]);

        $mensaje = 'El equipo de IMPULSATE te agregó directamente como proveedor en el evento "' . $evento->nombre . '". Ya apareces en el listado de proveedores.';
        Notificacion::crear($restaurantero->user_id, 'solicitud_aprobada', 'Agregado al evento', $mensaje);

        try {
            Mail::to($restaurantero->user->email)->send(
                new EventoSolicitudAprobadaMail($restaurantero->user, $evento, 'proveedor')
            );
        } catch (\Exception $e) {
            \Log::warning('Error enviando correo de alta manual de proveedor: ' . $e->getMessage());
        }

        return back()->with('success', 'Proveedor "' . $restaurantero->nombre_restaurante . '" agregado al evento correctamente.');
    }

    public function agregarComprador(Evento $evento, Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);

        $user = User::findOrFail($request->user_id);

        $yaRegistrado = DB::table('evento_usuario')
            ->where('evento_id', $evento->id)
            ->where('user_id',   $user->id)
            ->where('tipo',      'comprador')
            ->exists();

        if ($yaRegistrado) {
            return back()->withErrors(['error' => 'Este comprador ya está registrado en el evento.']);
        }

        DB::table('evento_usuario')->insert([
            'evento_id'     => $evento->id,
            'user_id'       => $user->id,
            'tipo'          => 'comprador',
            'estado'        => 'aprobado',
            'respondido_at' => now(),
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        $mensaje = 'El equipo de IMPULSATE te agregó directamente al evento "' . $evento->nombre . '". Ya puedes agendar citas con los proveedores.';
        Notificacion::crear($user->id, 'solicitud_aprobada', 'Agregado al evento', $mensaje);

        try {
            Mail::to($user->email)->send(
                new EventoSolicitudAprobadaMail($user, $evento, 'comprador')
            );
        } catch (\Exception $e) {
            \Log::warning('Error enviando correo de alta manual de comprador: ' . $e->getMessage());
        }

        return back()->with('success', 'Comprador "' . ($user->nombre_empresa ?? $user->name) . '" agregado al evento correctamente.');
    }

    public function eliminar(Evento $evento, User $user, Request $request)
    {
        $request->validate([
            'tipo'               => 'required|in:proveedor,comprador,expositor',
            'motivo_eliminacion' => 'required|string|max:500',
        ]);

        $tipo = $request->tipo;

        $registro = DB::table('evento_usuario')
            ->where('evento_id', $evento->id)
            ->where('user_id',   $user->id)
            ->where('tipo',      $tipo)
            ->where('estado',    'aprobado')
            ->first();

        if (!$registro) {
            return back()->withErrors(['error' => 'Registro no encontrado o no está aprobado.']);
        }

        if ($tipo === 'proveedor') {
            $restaurantero = $user->restaurantero;
            if ($restaurantero) {
                \App\Models\Cita::where('restaurantero_id', $restaurantero->id)
                    ->whereNotIn('estado', ['cancelada', 'rechazada', 'completada'])
                    ->update(['estado' => 'cancelada']);
            }
        } else {
            \App\Models\Cita::where('cliente_id', $user->id)
                ->whereNotIn('estado', ['cancelada', 'rechazada', 'completada'])
                ->update(['estado' => 'cancelada']);
        }

        DB::table('evento_usuario')
            ->where('evento_id', $evento->id)
            ->where('user_id',   $user->id)
            ->where('tipo',      $tipo)
            ->delete();

        $tipoTexto = $tipo === 'proveedor' ? 'como proveedor ' : '';
        Notificacion::crear(
            $user->id,
            'solicitud_rechazada',
            'Eliminado del evento',
            'Has sido eliminado ' . $tipoTexto . 'del evento "' . $evento->nombre . '". Motivo: ' . $request->motivo_eliminacion . '. Puedes volver a postularte.'
        );

        return back()->with('success', 'Usuario eliminado del evento.');
    }
}
