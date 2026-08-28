<?php

namespace App\Http\Controllers;

use App\Mail\PlantillaCorreoMail;
use App\Models\Evento;
use App\Models\Notificacion;
use App\Models\PlantillaCorreo;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
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

        if ($evento->fecha_hora_inicio_compradores && now()->lt($evento->fecha_hora_inicio_compradores)) {
            throw ValidationException::withMessages([
                'error' => 'El registro de compradores aun no ha abierto. Apertura: ' .
                    $evento->fecha_hora_inicio_compradores->format('d/m/Y H:i') . '.',
            ]);
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

        // Notificar a TODOS los administradores (antes solo al primero por ID)
        foreach (User::role('admin')->get() as $admin) {
            Notificacion::crear(
                $admin->id,
                'solicitud_registro',
                'Nueva solicitud de comprador',
                $user->name . ' solicita unirse al evento "' . $evento->nombre . '" como comprador.'
            );
        }

        // Notificación in-app al usuario
        Notificacion::crear(
            $user->id,
            'solicitud_registro',
            'Tu solicitud está en revisión',
            'Registramos tu solicitud para el evento "' . $evento->nombre . '". Te avisaremos por correo cuando sea aprobada.'
        );

        $this->enviarAcuseSolicitud($user, $evento, 'comprador');

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

        if ($evento->fecha_hora_inicio_proveedores && now()->lt($evento->fecha_hora_inicio_proveedores)) {
            throw ValidationException::withMessages([
                'error' => 'El registro de proveedores aun no ha abierto. Apertura: ' .
                    $evento->fecha_hora_inicio_proveedores->format('d/m/Y H:i') . '.',
            ]);
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

        // Notificar a TODOS los administradores (antes solo al primero por ID)
        foreach (User::role('admin')->get() as $admin) {
            Notificacion::crear(
                $admin->id,
                'solicitud_registro',
                'Nueva solicitud de proveedor',
                $user->name . ' solicita unirse al evento "' . $evento->nombre . '" como proveedor.'
            );
        }

        // Notificación in-app al usuario
        Notificacion::crear(
            $user->id,
            'solicitud_registro',
            'Tu solicitud está en revisión',
            'Registramos tu solicitud como proveedor para el evento "' . $evento->nombre . '". Te avisaremos por correo cuando sea aprobada.'
        );

        $this->enviarAcuseSolicitud($user, $evento, 'proveedor');

        return back()->with('success', 'Tu solicitud de registro como proveedor fue enviada. El administrador la aprobará pronto.');
    }

    public function registrarBazar(Request $request, Evento $evento)
    {
        if ($evento->tipo_evento !== 'bazar_exposicion') {
            throw ValidationException::withMessages([
                'error' => 'Este evento no es de tipo Bazar/Exposición.',
            ]);
        }

        if (!$evento->activa) {
            throw ValidationException::withMessages([
                'error' => 'Este evento no está activo.',
            ]);
        }

        if ($evento->fecha_hora_fin && now()->gt($evento->fecha_hora_fin)) {
            throw ValidationException::withMessages([
                'error' => 'Este evento ya ha finalizado.',
            ]);
        }

        // Verificar que la ventana de solicitudes esté abierta
        if (
            $evento->fecha_aceptacion_solicitudes &&
            now()->lt($evento->fecha_aceptacion_solicitudes)
        ) {
            throw ValidationException::withMessages([
                'error' => 'El registro aún no está abierto. Apertura: '
                    . $evento->fecha_aceptacion_solicitudes->format('d/m/Y H:i') . '.',
            ]);
        }

        $user = $request->user();

        // Verificar que el usuario tenga INE subida
        if (!$user->ine_path) {
            throw ValidationException::withMessages([
                'error' => 'Debes subir tu INE antes de registrarte al bazar.',
            ]);
        }

        // Verificar que el usuario tenga CSF subida y vigente (máx. 3 meses)
        if (!$user->csf_path || !$user->csf_fecha) {
            throw ValidationException::withMessages([
                'error' => 'Debes subir tu Constancia de Situación Fiscal (CSF) con su fecha de emisión.',
            ]);
        }

        $limiteFecha = \Carbon\Carbon::now()->subMonths(3);
        if (\Carbon\Carbon::parse($user->csf_fecha)->lt($limiteFecha)) {
            throw ValidationException::withMessages([
                'error' => 'Tu Constancia de Situación Fiscal (CSF) tiene más de 3 meses de antigüedad. Sube una versión reciente.',
            ]);
        }

        $registro = DB::table('evento_usuario')
            ->where('evento_id', $evento->id)
            ->where('user_id', $user->id)
            ->where('tipo', 'expositor')
            ->first();

        if ($registro) {
            if (in_array($registro->estado, ['pendiente', 'aprobado'])) {
                return back()->with('success', 'Ya tienes una solicitud registrada para este evento.');
            }
            DB::table('evento_usuario')
                ->where('evento_id', $evento->id)
                ->where('user_id', $user->id)
                ->where('tipo', 'expositor')
                ->update([
                    'estado'          => 'pendiente',
                    'motivo_rechazo'  => null,
                    'respondido_at'   => null,
                    'seleccionado'    => false,
                    'updated_at'      => now(),
                ]);
        } else {
            DB::table('evento_usuario')->insert([
                'evento_id'   => $evento->id,
                'user_id'     => $user->id,
                'tipo'        => 'expositor',
                'estado'      => 'pendiente',
                'seleccionado'=> false,
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);
        }

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
                \Log::warning("Error al enviar correo bazar_solicitud_recibida: " . $e->getMessage());
            }
        }

        return back()->with('success',
            'Tu solicitud fue enviada. Te notificaremos por correo cuando sea revisada.');
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
                \Log::warning('Plantilla "evento_solicitud_recibida" no encontrada o inactiva.');
                return;
            }

            Mail::to($user->email)->queue(new PlantillaCorreoMail($plantilla, [
                'nombre_usuario'    => $user->name,
                'nombre_evento'     => $evento->nombre,
                'tipo_participante' => $tipo === 'proveedor' ? 'proveedor' : 'comprador',
            ]));
        } catch (\Exception $e) {
            \Log::warning('Error enviando acuse de solicitud al evento: ' . $e->getMessage());
        }
    }
}
