<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Edicion extends Model
{
    use HasFactory;

    protected $table = 'ediciones';

    protected $fillable = [
        'nombre',
        'sector',
        'descripcion',
        'fecha_inicio',
        'fecha_inicio_agenda',
        'fecha_fin_agenda',
        'fecha_corte',
        'activa',
    ];

    protected function casts(): array
    {
        return [
            'activa'              => 'boolean',
            'fecha_inicio'        => 'date',
            'fecha_inicio_agenda' => 'date',
            'fecha_fin_agenda'    => 'date',
            'fecha_corte'         => 'date',
        ];
    }

    public static function activa(): ?self
    {
        return static::where('activa', true)->first();
    }

    public function citas()
    {
        return $this->hasMany(Cita::class);
    }

    public function restauranteros()
    {
        return $this->hasMany(Restaurantero::class);
    }
}
