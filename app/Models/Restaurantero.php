<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Restaurantero extends Model
{
    use HasFactory;

    public static array $categorias = [
        'Alimentos y Bebidas',
        'Tecnología',
        'Salud y Bienestar',
        'Servicios Profesionales',
        'Manufactura',
        'Comercio',
        'Construcción',
        'Educación',
        'Turismo',
        'Logística',
        'Finanzas',
        'Otro',
    ];

    protected $fillable = [
        'edicion_id',
        'user_id',
        'nombre_restaurante',
        'descripcion',
        'telefono',
        'direccion',
        'municipio',
        'categoria',
        'logo_path',
        'foto_path',
        'productos_top',
        'categorias_json',
        'rfc',
        'sitio_web',
        'redes_sociales',
        'activo',
        'aprobado',
        'rechazado',
        'motivo_rechazo',
        'solicitado_aprobacion_at',
    ];

    protected function casts(): array
    {
        return [
            'activo'                   => 'boolean',
            'aprobado'                 => 'boolean',
            'rechazado'                => 'boolean',
            'productos_top'            => 'array',
            'categorias_json'          => 'array',
            'redes_sociales'           => 'array',
            'solicitado_aprobacion_at' => 'datetime',
        ];
    }

    public function edicion()
    {
        return $this->belongsTo(Edicion::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function servicios()
    {
        return $this->hasMany(Servicio::class);
    }

    public function horarios()
    {
        return $this->hasMany(Horario::class);
    }

    public function citas()
    {
        return $this->hasMany(Cita::class);
    }
}
