<?php

namespace App\Services;

use App\Exceptions\ReglaDeNegocioException;
use App\Models\Evento;
use App\Models\Horario;
use App\Models\Restaurantero;
use App\Models\Servicio;
use App\Models\User;

/**
 * Pasos de onboarding (aviso de privacidad, seleccion de rol, agregar rol)
 * compartidos por la web (AvisoAceptacionController, SeleccionarRolController,
 * CompletarPerfilController::agregarRol) y la API v1.
 */
class OnboardingService
{
    /** Valores que acepta la pantalla /seleccionar-rol de la web. */
    public const ROLES_SELECCIONABLES = ['comprador', 'proveedor', 'ambos'];

    /** Valores de /perfil/agregar-rol. */
    public const ROLES_AGREGABLES = ['comprador', 'proveedor'];

    public const MENSAJE_AVISO = 'Debes aceptar el Aviso de Privacidad para continuar.';
    public const MENSAJE_ROL = 'Selecciona si participas como comprador o proveedor para continuar.';
    public const MENSAJE_PERFIL = 'Completa tu perfil antes de continuar. Faltan datos obligatorios.';

    public static function reglasAviso(): array
    {
        return [
            'acepta' => ['required', 'accepted'],
        ];
    }

    public static function mensajesAviso(): array
    {
        return [
            'acepta.required' => self::MENSAJE_AVISO,
            'acepta.accepted' => self::MENSAJE_AVISO,
        ];
    }

    public function aceptarAviso(User $user): void
    {
        $user->update(['acepta_aviso_at' => now()]);
    }

    /**
     * Lo que hace POST /seleccionar-rol: asigna los roles de Spatie, fija el
     * rol activo y crea el perfil de negocio "stub" del proveedor.
     */
    public function seleccionarRol(User $user, string $rol): void
    {
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
    }

    /** Lo que hace POST /perfil/agregar-rol. */
    public function agregarRol(User $user, string $rol): void
    {
        if ($rol === 'proveedor') {
            if (!$user->hasRole('restaurantero')) {
                $user->assignRole('restaurantero');
            }
            $this->crearPerfilProveedor($user);
        } else {
            if (!$user->hasRole('cliente')) {
                $user->assignRole('cliente');
            }
        }
    }

    /**
     * Perfil de negocio vacio para que el admin vea al proveedor desde ya.
     * NO se registra a ningun evento: el proveedor lo elige desde su panel.
     */
    public function crearPerfilProveedor(User $user): void
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

        $user->setRelation('restaurantero', $restaurantero);
    }

    /*
    |--------------------------------------------------------------------------
    | Reglas de los middlewares web, para la API
    |--------------------------------------------------------------------------
    | En la web las imponen EnsureAvisoAceptado / EnsureRolSeleccionado /
    | EnsureProfileComplete con redirecciones antes de llegar al controlador.
    | La API no pasa por ellos: estos metodos lanzan la misma regla como 422.
    */

    public function exigirAviso(User $user): void
    {
        if (!$user->onboarding()['aviso_aceptado']) {
            throw new ReglaDeNegocioException(self::MENSAJE_AVISO, 'onboarding');
        }
    }

    public function exigirAvisoYRol(User $user): void
    {
        $this->exigirAviso($user);

        if (!$user->onboarding()['rol_seleccionado']) {
            throw new ReglaDeNegocioException(self::MENSAJE_ROL, 'onboarding');
        }
    }

    public function exigirOnboardingCompleto(User $user): void
    {
        $this->exigirAvisoYRol($user);

        if (!$user->onboarding()['perfil_completo']) {
            throw new ReglaDeNegocioException(self::MENSAJE_PERFIL, 'error');
        }
    }
}
