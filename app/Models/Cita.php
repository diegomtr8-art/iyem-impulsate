<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Cita extends Model
{
    use HasFactory;

    protected $fillable = [
        'edicion_id',
        'restaurantero_id',
        'servicio_id',
        'cliente_id',
        'inicio',
        'fin',
        'estado',
        'notas',
        'propuesta_inicio',
        'propuesta_fin',
        'token_confirmacion',
        'recordatorio_24h_enviado',
        'recordatorio_2h_enviado',
    ];

    protected function casts(): array
    {
        return [
            'inicio'                   => 'datetime',
            'fin'                      => 'datetime',
            'propuesta_inicio'         => 'datetime',
            'propuesta_fin'            => 'datetime',
            'recordatorio_24h_enviado' => 'boolean',
            'recordatorio_2h_enviado'  => 'boolean',
        ];
    }

    public function edicion()
    {
        return $this->belongsTo(Edicion::class);
    }

    public function restaurantero()
    {
        return $this->belongsTo(Restaurantero::class);
    }

    public function servicio()
    {
        return $this->belongsTo(Servicio::class);
    }

    public function cliente()
    {
        return $this->belongsTo(User::class, 'cliente_id');
    }
}
