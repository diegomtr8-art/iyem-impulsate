<?php

namespace App\Services;

use App\Exceptions\ReglaDeNegocioException;
use App\Mail\PlantillaCorreoMail;
use App\Models\Evento;
use App\Models\Notificacion;
use App\Models\PlantillaCorreo;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Registro de un usuario a un evento (comprador, proveedor o expositor de bazar).
 *
 * Es la UNICA fuente de las reglas de registro: la web (EventoRegistroController)
 * y la API (Api\V1\EventoController) llaman aqui, y EventoResource usa
 * motivoBloqueo() para decirle a la app si puede registrarse sin que la app
 * tenga que reimplementar las ventanas.
 */
class RegistroEventoService
{
    public const TIPOS = ['comprador', 'proveedor', 'expositor'];

    /**
     * Motivo por el que $user no puede registrarse como $tipo, o null si puede.
     * El orden de las comprobaciones es el mismo que tenia el controlador web,
     * para que el mensaje que ve el usuario no cambie.
     */
    public function motivoBloqueo(User $user, Evento $evento, string $tipo): ?string
    {
        return match ($tipo) {
            'comprador' => $this->motivoComprador($user, $evento),
            'proveedor' => $this->motivoProveedor($user, $evento),
            'expositor' => $this->motivoExpositor($user, $evento),
            default     => 'Tipo de registro no valido.',
        };
    }

    /**
     * El tipo de registro que le corresponde al usuario en este evento, segun
     * el tipo de evento y su rol activo. Null si ninguno aplica (p. ej. admin).
     */
    public function tipoParaUsuario(User $user, Evento $evento): ?string
    {
        if ($user->hasRole('admin') || $user->hasRole('super-admin')) {
            return null;
        }

        if ($evento->esBazar()) {
            return 'expositor';
        }

        $esComprador = $user->hasRole('cliente');
        $esProveedor = $user->hasRole('restaurantero');

        if ($esComprador && $esProveedor) {
            return $user->active_role === 'proveedor' ? 'proveedor' : 'comprador';
        }

        return $esProveedor ? 'proveedor' : ($esComprador ? 'comprador' : null);
    }

    /**
     * @return array{estado: string, mensaje: string}
     *         estado: creado | repostulado | ya_pendiente | ya_aprobado
     */
    public function registrar(User $user, Evento $evento, string $tipo): array
    {
        if ($motivo = $this->motivoBloqueo($user, $evento, $tipo)) {
            throw new ReglaDeNegocioException($motivo);
        }

        $estado = DB::transaction(function () use ($user, $evento, $tipo) {
            $registro = DB::table('evento_usuario')
                ->where('evento_id', $evento->id)
                ->where('user_id', $user->id)
                ->where('tipo', $tipo)
                ->lockForUpdate()
                ->first();

            if ($registro && in_array($registro->estado, ['pendiente', 'aprobado'])) {
                return 'ya_' . $registro->estado;
            }

            if ($registro) {
                $cambios = [
                    'estado'         => 'pendiente',
                    'motivo_rechazo' => null,
                    'respondido_at'  => null,
                    'updated_at'     => now(),
                ];
                if ($tipo === 'expositor') {
                    $cambios['seleccionado'] = false;
                }
                DB::table('evento_usuario')
                    ->where('id', $registro->id)
                    ->update($cambios);

                return 'repostulado';
            }

            $fila = [
                'evento_id'  => $evento->id,
                'user_id'    => $user->id,
                'tipo'       => $tipo,
                'estado'     => 'pendiente',
                'created_at' => now(),
                'updated_at' => now(),
            ];
            if ($tipo === 'expositor') {
                $fila['seleccionado'] = false;
            }
            DB::table('evento_usuario')->insert($fila);

            return 'creado';
        });

        if (in_array($estado, ['creado', 'repostulado'])) {
            $this->notificar($user, $evento, $tipo);
        }

        return ['estado' => $estado, 'mensaje' => $this->mensaje($tipo, $estado)];
    }

    public function registrarComprador(User $user, Evento $evento): array
    {
        return $this->registrar($user, $evento, 'comprador');
    }

    public function registrarProveedor(User $user, Evento $evento): array
    {
        return $this->registrar($user, $evento, 'proveedor');
    }

    public function registrarExpositor(User $user, Evento $evento): array
    {
        return $this->registrar($user, $evento, 'expositor');
    }

    private function motivoComprador(User $user, Evento $evento): ?string
    {
        if (!$user->hasRole('cliente')) {
            return 'Necesitas el rol de comprador para registrarte.';
        }

        if ($evento->fecha_hora_fin && now()->gt($evento->fecha_hora_fin)) {
            return 'Este evento ya ha finalizado.';
        }

        if ($evento->fecha_hora_inicio_compradores && now()->lt($evento->fecha_hora_inicio_compradores)) {
            return 'El registro de compradores aun no ha abierto. Apertura: ' .
                $evento->fecha_hora_inicio_compradores->format('d/m/Y H:i') . '.';
        }

        $finCompradores = $evento->fecha_hora_fin_compradores ?? $evento->fecha_hora_fin;
        if ($finCompradores && now()->gt($finCompradores)) {
            return 'El período de registro de compradores ya cerró.';
        }

        return $this->motivoPerfil($user);
    }

    private function motivoProveedor(User $user, Evento $evento): ?string
    {
        if (!$user->hasRole('restaurantero')) {
            return 'Necesitas el rol de proveedor para registrarte.';
        }

        if (!$user->restaurantero) {
            return 'Primero debes completar tu perfil de proveedor.';
        }

        if ($evento->fecha_hora_fin && now()->gt($evento->fecha_hora_fin)) {
            return 'Este evento ya ha finalizado.';
        }

        if ($evento->fecha_hora_inicio_proveedores && now()->lt($evento->fecha_hora_inicio_proveedores)) {
            return 'El registro de proveedores aun no ha abierto. Apertura: ' .
                $evento->fecha_hora_inicio_proveedores->format('d/m/Y H:i') . '.';
        }

        if ($evento->fecha_hora_fin_proveedores && now()->gt($evento->fecha_hora_fin_proveedores)) {
            return 'El período de registro de proveedores ya cerró el ' .
                $evento->fecha_hora_fin_proveedores->format('d/m/Y H:i') . '.';
        }

        return $this->motivoPerfil($user);
    }

    private function motivoExpositor(User $user, Evento $evento): ?string
    {
        if ($evento->tipo_evento !== 'bazar_exposicion') {
            return 'Este evento no es de tipo Bazar/Exposición.';
        }

        if (!$evento->activa) {
            return 'Este evento no está activo.';
        }

        if ($evento->fecha_hora_fin && now()->gt($evento->fecha_hora_fin)) {
            return 'Este evento ya ha finalizado.';
        }

        if ($evento->fecha_aceptacion_solicitudes && now()->lt($evento->fecha_aceptacion_solicitudes)) {
            return 'El registro aún no está abierto. Apertura: '
                . $evento->fecha_aceptacion_solicitudes->format('d/m/Y H:i') . '.';
        }

        if (!$user->ine_path) {
            return 'Debes subir tu INE antes de registrarte al bazar.';
        }

        if (!$user->csf_path || !$user->csf_fecha) {
            return 'Debes subir tu Constancia de Situación Fiscal (CSF) con su fecha de emisión.';
        }

        if (Carbon::parse($user->csf_fecha)->lt(Carbon::now()->subMonths(3))) {
            return 'Tu Constancia de Situación Fiscal (CSF) tiene más de 3 meses de antigüedad. Sube una versión reciente.';
        }

        return null;
    }

    /**
     * En la web lo exige EnsureProfileComplete antes de llegar al controlador;
     * la API no pasa por ese middleware, asi que la regla tiene que vivir aqui.
     */
    private function motivoPerfil(User $user): ?string
    {
        return $user->perfilCompletoEfectivo()
            ? null
            : 'Completa tu perfil antes de continuar. Faltan datos obligatorios.';
    }

    private function mensaje(string $tipo, string $estado): string
    {
        if ($tipo === 'expositor') {
            return $estado === 'ya_pendiente' || $estado === 'ya_aprobado'
                ? 'Ya tienes una solicitud registrada para este evento.'
                : 'Tu solicitud fue enviada. Te notificaremos por correo cuando sea revisada.';
        }

        return match ($estado) {
            'ya_pendiente' => 'Tu solicitud ya está en revisión. Te avisaremos cuando sea aprobada.',
            'ya_aprobado'  => "Ya estás aprobado en este evento como {$tipo}.",
            default        => $tipo === 'proveedor'
                ? 'Tu solicitud de registro como proveedor fue enviada. El administrador la aprobará pronto.'
                : 'Tu solicitud de registro fue enviada. El administrador la revisará pronto.',
        };
    }

    private function notificar(User $user, Evento $evento, string $tipo): void
    {
        if ($tipo === 'expositor') {
            foreach (User::role('admin')->get() as $admin) {
                Notificacion::crear(
                    $admin->id,
                    'solicitud_registro',
                    'Nueva solicitud de bazar',
                    $user->name . ' solicita participar como expositor en "' . $evento->nombre . '".'
                );
            }

            $plantilla = PlantillaCorreo::paraClave('bazar_solicitud_recibida');
            if ($plantilla) {
                try {
                    Mail::to($user->email)->queue(new PlantillaCorreoMail($plantilla, [
                        'nombre_usuario' => $user->name,
                        'nombre_evento'  => $evento->nombre,
                    ]));
                } catch (\Exception $e) {
                    Log::warning('Error al enviar correo bazar_solicitud_recibida: ' . $e->getMessage());
                }
            }

            return;
        }

        foreach (User::role('admin')->get() as $admin) {
            Notificacion::crear(
                $admin->id,
                'solicitud_registro',
                'Nueva solicitud de ' . $tipo,
                $user->name . ' solicita unirse al evento "' . $evento->nombre . '" como ' . $tipo . '.'
            );
        }

        Notificacion::crear(
            $user->id,
            'solicitud_registro',
            'Tu solicitud está en revisión',
            $tipo === 'proveedor'
                ? 'Registramos tu solicitud como proveedor para el evento "' . $evento->nombre . '". Te avisaremos por correo cuando sea aprobada.'
                : 'Registramos tu solicitud para el evento "' . $evento->nombre . '". Te avisaremos por correo cuando sea aprobada.'
        );

        $this->enviarAcuseSolicitud($user, $evento, $tipo);
    }

    /**
     * Acuse de recibo al registrarse al encuentro de negocios.
     * Nunca debe lanzar excepción: un fallo de SMTP no puede tumbar el registro.
     */
    private function enviarAcuseSolicitud(User $user, Evento $evento, string $tipo): void
    {
        if (!$user->email) return;

        try {
            $plantilla = PlantillaCorreo::paraClave('evento_solicitud_recibida');
            if (!$plantilla) {
                Log::warning('Plantilla "evento_solicitud_recibida" no encontrada o inactiva.');
                return;
            }

            Mail::to($user->email)->queue(new PlantillaCorreoMail($plantilla, [
                'nombre_usuario'    => $user->name,
                'nombre_evento'     => $evento->nombre,
                'tipo_participante' => $tipo === 'proveedor' ? 'proveedor' : 'comprador',
            ]));
        } catch (\Exception $e) {
            Log::warning('Error enviando acuse de solicitud al evento: ' . $e->getMessage());
        }
    }
}
