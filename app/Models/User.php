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
