<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Servicio extends Model
{
    use HasFactory;

    protected $fillable = [
        'restaurantero_id',
        'nombre',
        'descripcion',
        'duracion_minutos',
        'precio',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
            'precio' => 'decimal:2',
        ];
    }

    public function restaurantero()
    {
        return $this->belongsTo(Restaurantero::class);
    }

    public function citas()
    {
        return $this->hasMany(Cita::class);
    }
}
