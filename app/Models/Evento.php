<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Evento extends Model
{
    use HasFactory;

    protected $table = 'eventos';

    protected $fillable = [
        'nombre',
        'sector_economico',
        'descripcion',
        'fecha_inicio',
        'fecha_corte',
        'activa',
        'fecha_hora_inicio',
        'fecha_hora_fin',
        'fecha_hora_inicio_proveedores',
        'fecha_hora_inicio_compradores',
        'max_citas_por_comprador',
        'tiempo_entre_citas_minutos',
        // Legacy agenda fields (se mantienen por compatibilidad)
        'fecha_inicio_agenda',
        'fecha_fin_agenda',
    ];

    protected function casts(): array
    {
        return [
            'activa'                         => 'boolean',
            'fecha_inicio'                   => 'date',
            'fecha_corte'                    => 'date',
            'fecha_inicio_agenda'            => 'date',
            'fecha_fin_agenda'               => 'date',
            'fecha_hora_inicio'              => 'datetime',
            'fecha_hora_fin'                 => 'datetime',
            'fecha_hora_inicio_proveedores'  => 'datetime',
            'fecha_hora_inicio_compradores'  => 'datetime',
        ];
    }

    public static function activo(): ?self
    {
        return static::where('activa', true)
            ->where(function ($q) {
                $q->whereNull('fecha_hora_inicio')
                  ->orWhere('fecha_hora_inicio', '<=', now());
            })
            ->where(function ($q) {
                $q->whereNull('fecha_hora_fin')
                  ->orWhere('fecha_hora_fin', '>=', now());
            })
            ->first();
    }

    public function citas()
    {
        return $this->hasMany(Cita::class);
    }

    public function restauranteros()
    {
        return $this->hasMany(Restaurantero::class);
    }

    public function compradores()
    {
        return $this->belongsToMany(User::class, 'evento_usuario')
                    ->wherePivot('tipo', 'comprador')
                    ->withTimestamps();
    }

    public function proveedores()
    {
        return $this->belongsToMany(User::class, 'evento_usuario')
                    ->wherePivot('tipo', 'proveedor')
                    ->withTimestamps();
    }
}
