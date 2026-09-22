<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Jetstream\HasProfilePhoto;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens;

    /** @use HasFactory<UserFactory> */
    use HasFactory;

    use HasProfilePhoto;
    use Notifiable;
    use TwoFactorAuthenticatable;
    use HasRoles;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'telefono',
        'curp',
        'rfc',
        'genero',
        'municipio',
        'nombre_empresa',
        'active_role',
        'rol_seleccionado',
        'necesidades',
        'perfil_completo',
        'es_restaurantero',
        'sitio_web',
        'acepta_aviso_at',
        'camara_asociacion',
        'nombre_establecimiento',
        'ine_path',
        'csf_path',
        'csf_fecha',
    ];

    public function restaurantero()
    {
        return $this->hasOne(Restaurantero::class);
    }

    public function citasComoCliente()
    {
        return $this->hasMany(Cita::class, 'cliente_id');
    }

    public function isAdmin(): bool
    {
        return $this->hasRole('admin');
    }

    public function isRestaurantero(): bool
    {
        return $this->hasRole('restaurantero');
    }

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_recovery_codes',
        'two_factor_secret',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array<int, string>
     */
    protected $appends = [
        'profile_photo_url',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    public function tieneDualRol(): bool
    {
        return $this->hasRole('cliente') && $this->hasRole('restaurantero');
    }

    /**
     * Los proveedores puros se miden por el perfil del negocio (Restaurantero),
     * no por el flag generico de users: ese puede quedar en true por datos viejos
     * aunque el negocio (logo, productos, etc.) siga incompleto.
     */
    public function perfilCompletoEfectivo(): bool
    {
        return $this->esSoloProveedor()
            ? (bool) $this->restaurantero?->perfil_completo
            : (bool) $this->perfil_completo;
    }

    public function esSoloProveedor(): bool
    {
        return $this->hasRole('restaurantero') && !$this->hasRole('cliente');
    }

    /**
     * Lo que en la web resuelven EnsureAvisoAceptado, EnsureRolSeleccionado y
     * EnsureProfileComplete con redirecciones, expresado como dato para la API.
     * El orden de siguiente_paso es el mismo en que actuan esos middlewares.
     */
    public function onboarding(): array
    {
        $esAdmin = $this->hasRole('admin') || $this->hasRole('super-admin');

        $avisoAceptado   = $esAdmin || !is_null($this->acepta_aviso_at);
        $rolSeleccionado = $esAdmin || (bool) $this->rol_seleccionado;
        $perfilCompleto  = $esAdmin || $this->perfilCompletoEfectivo();

        $siguiente = match (true) {
            !$avisoAceptado   => 'aceptar_aviso',
            !$rolSeleccionado => 'seleccionar_rol',
            !$perfilCompleto  => 'completar_perfil',
            default           => 'ninguno',
        };

        return [
            'aviso_aceptado'   => $avisoAceptado,
            'rol_seleccionado' => $rolSeleccionado,
            'perfil_completo'  => $perfilCompleto,
            'siguiente_paso'   => $siguiente,
            'rol_activo'       => $esAdmin ? 'admin' : $this->active_role,
        ];
    }

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'acepta_aviso_at'   => 'datetime',
            'es_restaurantero'  => 'boolean',
            'password' => 'hashed',
        ];
    }

    /**
     * Si el SMTP falla, registra el error pero no lanza excepción al usuario (evita 500).
     */
    public function sendEmailVerificationNotification(): void
    {
        try {
            parent::sendEmailVerificationNotification();
        } catch (\Exception $e) {
            \Log::error('Error al enviar correo de verificación a ' . $this->email . ': ' . $e->getMessage());
        }
    }
}
