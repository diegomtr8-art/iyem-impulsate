<?php

namespace App\Services\Admin;

use App\Exceptions\ReglaDeNegocioException;
use App\Mail\EventoSolicitudAprobadaMail;
use App\Mail\EventoSolicitudRechazadaMail;
use App\Models\Cita;
use App\Models\Evento;
use App\Models\Notificacion;
use App\Models\Restaurantero;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Solicitudes de registro a un evento (tabla evento_usuario): listado con el
 * perfil del solicitante, aprobar, rechazar, aprobar en bloque, regresar a
 * pendiente, alta manual y baja. Mismos correos y notificaciones que antes.
 * Lo usan Admin\EventoSolicitudesController (web) y la API v1.
 */
class SolicitudesEventoService
{
    public const TIPOS = ['proveedor', 'comprador', 'expositor'];
    public const ESTADOS = ['pendiente', 'aprobado', 'rechazado'];

    /** Todos los registros del evento ya mapeados (forma de la pagina Admin/Eventos/Solicitudes). */
    public function registros(Evento $evento, ?int $soloUserId = null): Collection
    {
        $registros = DB::table('evento_usuario')
            ->where('evento_id', $evento->id)
            ->when($soloUserId, fn ($q) => $q->where('user_id', $soloUserId))
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

        return $registros->map(fn ($reg) => $this->mapear($reg, $users, $citasHistoricoPorUser));
    }

    /** Lo que recibe la pagina web: registros por estado + candidatos para el alta manual. */
    public function datosPagina(Evento $evento): array
    {
        $registros = DB::table('evento_usuario')->where('evento_id', $evento->id)->get();
        $mapeados  = $this->registros($evento);

        return [
            'evento'           => $evento->only(['id', 'nombre', 'activa', 'tipo_evento']),
            'pendientes'       => $mapeados->where('estado', 'pendiente')->values(),
            'aprobados'        => $mapeados->where('estado', 'aprobado')->values(),
            'rechazados'       => $mapeados->where('estado', 'rechazado')->values(),
            'todosProveedores' => $this->proveedoresSinRegistro($evento, $registros),
            'todosCompradores' => $this->compradoresSinRegistro($evento, $registros),
        ];
    }

    /**
     * Proveedores/compradores que aún no tienen ningún registro (de cualquier
     * estado) en este evento, para el formulario de alta manual.
     */
    public function proveedoresSinRegistro(Evento $evento, ?Collection $registros = null): Collection
    {
        $registros ??= DB::table('evento_usuario')->where('evento_id', $evento->id)->get();

        return Restaurantero::whereNotIn('user_id', $registros->where('tipo', 'proveedor')->pluck('user_id'))
            ->orderBy('nombre_restaurante')
            ->get(['id', 'nombre_restaurante', 'user_id']);
    }

    public function compradoresSinRegistro(Evento $evento, ?Collection $registros = null): Collection
    {
        $registros ??= DB::table('evento_usuario')->where('evento_id', $evento->id)->get();

        return User::whereDoesntHave('roles', fn ($q) => $q->where('name', 'admin'))
            ->whereNotIn('id', $registros->where('tipo', 'comprador')->pluck('user_id'))
            ->orderBy('name')
            ->get(['id', 'name', 'nombre_empresa']);
    }

    private function mapear(object $reg, Collection $users, Collection $citasHistoricoPorUser): array
    {
        $u = $users[$reg->user_id] ?? null;

        return [
            'id'             => $reg->id,
            'user_id'        => $reg->user_id,
            'tipo'           => $reg->tipo,
            'estado'         => $reg->estado,
            'motivo_rechazo' => $reg->motivo_rechazo,
            'respondido_at'  => $reg->respondido_at,
            'created_at'     => $reg->created_at,
            'user'           => $u ? [
                'id'                => $u->id,
                'name'              => $u->name,
                'email'             => $u->email,
                'telefono'          => $u->telefono,
                'sitio_web'         => $u->sitio_web,
                'necesidades'       => $u->necesidades,
                'empresa_nombre'    => $u->empresa_nombre ?? null,
                'rfc'               => $u->rfc ?? null,
                'nombre_empresa'    => $u->nombre_empresa ?? null,
                'camara_asociacion' => $u->camara_asociacion ?? null,
                'nombre_establecimiento' => $u->nombre_establecimiento ?? null,
                'created_at'        => $u->created_at,
                'citas_historico'   => $citasHistoricoPorUser[$reg->user_id] ?? 0,
                // Booleanos en vez de rutas: el frontend solo los usa como
                // bandera v-if, y los enlaces reales van por route('documentos.ver').
                // No hay razon para exponer rutas internas del filesystem.
                'tiene_ine' => !empty($u->ine_path),
                'tiene_csf' => !empty($u->csf_path),
                'csf_fecha' => $u->csf_fecha ?? null,
            ] : null,
            'restaurantero'  => ($reg->tipo === 'proveedor' && $u && $u->restaurantero)
                ? $this->negocio($u->restaurantero)
                : null,
            'citas_activas'  => $this->citasActivas($u, $reg->tipo),
        ];
    }

    private function negocio(Restaurantero $r): array
    {
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
            'citas_count'            => Cita::where('restaurantero_id', $r->id)->count(),
        ];
    }

    private function citasActivas(?User $u, string $tipo): int
    {
        if (!$u) return 0;
        if ($tipo === 'proveedor') {
            $r = $u->restaurantero;
            if (!$r) return 0;
            return Cita::where('restaurantero_id', $r->id)
                ->whereNotIn('estado', ['cancelada', 'rechazada'])
                ->count();
        }

        return Cita::where('cliente_id', $u->id)
            ->whereNotIn('estado', ['cancelada', 'rechazada'])
            ->count();
    }

    private function query(Evento $evento, User $user, string $tipo)
    {
        return DB::table('evento_usuario')
            ->where('evento_id', $evento->id)
            ->where('user_id', $user->id)
            ->where('tipo', $tipo);
    }

    public function aprobar(Evento $evento, User $user, string $tipo): void
    {
        if (!$this->query($evento, $user, $tipo)->first()) {
            throw new ReglaDeNegocioException('Solicitud no encontrada.');
        }

        $this->query($evento, $user, $tipo)->update(['estado' => 'aprobado', 'respondido_at' => now()]);

        // Si es proveedor y tiene restaurantero, vincularlo al evento
        if ($tipo === 'proveedor') {
            $user->restaurantero?->update(['edicion_id' => $evento->id]);
            $mensaje = '¡Fuiste aprobado como proveedor en el evento "' . $evento->nombre . '"! Ya apareces en el listado de proveedores.';
        } elseif ($tipo === 'expositor') {
            $mensaje = '¡Fuiste aprobado como expositor en el bazar "' . $evento->nombre . '"! Recibirás más información sobre tu espacio.';
        } else {
            $mensaje = '¡Fuiste aprobado en el evento "' . $evento->nombre . '"! Ya puedes agendar citas con los proveedores.';
        }

        Notificacion::crear($user->id, 'solicitud_aprobada', 'Solicitud aprobada', $mensaje);

        try {
            Mail::to($user->email)->send(new EventoSolicitudAprobadaMail($user, $evento, $tipo));
        } catch (\Exception $e) {
            Log::warning('Error enviando correo de aprobación de evento: ' . $e->getMessage());
        }
    }

    public function rechazar(Evento $evento, User $user, string $tipo, string $motivo): void
    {
        if (!$this->query($evento, $user, $tipo)->first()) {
            throw new ReglaDeNegocioException('Solicitud no encontrada.');
        }

        $this->query($evento, $user, $tipo)->update([
            'estado'         => 'rechazado',
            'motivo_rechazo' => $motivo,
            'respondido_at'  => now(),
        ]);

        $tipoTexto = $tipo === 'proveedor' ? 'como proveedor ' : '';
        Notificacion::crear(
            $user->id,
            'solicitud_rechazada',
            'Solicitud rechazada',
            'Tu solicitud ' . $tipoTexto . 'para el evento "' . $evento->nombre . '" fue rechazada. Motivo: ' . $motivo
        );

        try {
            Mail::to($user->email)->send(new EventoSolicitudRechazadaMail($user, $evento, $tipo, $motivo));
        } catch (\Exception $e) {
            Log::warning('Error enviando correo de rechazo de evento: ' . $e->getMessage());
        }
    }

    /** Aprueba todas las pendientes de un tipo. Devuelve cuantas. */
    public function aprobarTodos(Evento $evento, string $tipo): int
    {
        $pendientes = DB::table('evento_usuario')
            ->where('evento_id', $evento->id)
            ->where('tipo', $tipo)
            ->where('estado', 'pendiente')
            ->get();

        if ($pendientes->isEmpty()) {
            return 0;
        }

        DB::table('evento_usuario')
            ->where('evento_id', $evento->id)
            ->where('tipo', $tipo)
            ->where('estado', 'pendiente')
            ->update(['estado' => 'aprobado', 'respondido_at' => now()]);

        $esProveedor = $tipo === 'proveedor';

        foreach ($pendientes as $reg) {
            $user = User::find($reg->user_id);
            if (!$user) continue;

            if ($esProveedor) {
                $user->restaurantero?->update(['edicion_id' => $evento->id]);
                $mensaje = '¡Fuiste aprobado como proveedor en el evento "' . $evento->nombre . '"! Ya apareces en el listado.';
            } else {
                $mensaje = '¡Fuiste aprobado en el evento "' . $evento->nombre . '"! Ya puedes agendar citas.';
            }

            Notificacion::crear($user->id, 'solicitud_aprobada', 'Solicitud aprobada', $mensaje);

            try {
                Mail::to($user->email)->send(new EventoSolicitudAprobadaMail($user, $evento, $reg->tipo));
            } catch (\Exception $e) {
                Log::warning('Correo aprobación masiva fallido para user ' . $user->id . ': ' . $e->getMessage());
            }
        }

        return $pendientes->count();
    }

    /** Regresa una solicitud RECHAZADA a pendiente. Devuelve cuantas filas cambiaron (0 o 1). */
    public function revertirPendiente(Evento $evento, User $user, string $tipo): int
    {
        return $this->query($evento, $user, $tipo)
            ->where('estado', 'rechazado')
            ->update(['estado' => 'pendiente', 'motivo_rechazo' => null, 'respondido_at' => null]);
    }

    public function agregarProveedor(Evento $evento, Restaurantero $restaurantero): void
    {
        $yaRegistrado = DB::table('evento_usuario')
            ->where('evento_id', $evento->id)
            ->where('user_id', $restaurantero->user_id)
            ->where('tipo', 'proveedor')
            ->exists();

        if ($yaRegistrado) {
            throw new ReglaDeNegocioException('Este proveedor ya está registrado en el evento.');
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
            Log::warning('Error enviando correo de alta manual de proveedor: ' . $e->getMessage());
        }
    }

    public function agregarComprador(Evento $evento, User $user): void
    {
        if ($this->query($evento, $user, 'comprador')->exists()) {
            throw new ReglaDeNegocioException('Este comprador ya está registrado en el evento.');
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
            Mail::to($user->email)->send(new EventoSolicitudAprobadaMail($user, $evento, 'comprador'));
        } catch (\Exception $e) {
            Log::warning('Error enviando correo de alta manual de comprador: ' . $e->getMessage());
        }
    }

    /**
     * Saca del evento a un participante APROBADO y cancela sus citas vivas.
     * OJO (comportamiento heredado de la web): cancela las citas del usuario en
     * TODOS los eventos, no solo en este.
     */
    public function eliminar(Evento $evento, User $user, string $tipo, string $motivo): void
    {
        if (!$this->query($evento, $user, $tipo)->where('estado', 'aprobado')->first()) {
            throw new ReglaDeNegocioException('Registro no encontrado o no está aprobado.');
        }

        if ($tipo === 'proveedor') {
            $restaurantero = $user->restaurantero;
            if ($restaurantero) {
                Cita::where('restaurantero_id', $restaurantero->id)
                    ->whereNotIn('estado', ['cancelada', 'rechazada', 'completada'])
                    ->update(['estado' => 'cancelada']);
            }
        } else {
            Cita::where('cliente_id', $user->id)
                ->whereNotIn('estado', ['cancelada', 'rechazada', 'completada'])
                ->update(['estado' => 'cancelada']);
        }

        $this->query($evento, $user, $tipo)->delete();

        $tipoTexto = $tipo === 'proveedor' ? 'como proveedor ' : '';
        Notificacion::crear(
            $user->id,
            'solicitud_rechazada',
            'Eliminado del evento',
            'Has sido eliminado ' . $tipoTexto . 'del evento "' . $evento->nombre . '". Motivo: ' . $motivo . '. Puedes volver a postularte.'
        );
    }
}
