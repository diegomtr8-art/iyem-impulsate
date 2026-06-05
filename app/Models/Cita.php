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
        'mesa',
        'estado_tv',
        'hora_real_inicio',
        'hora_real_fin',
        'llamada_at',
    ];

    protected function casts(): array
    {
        return [
            'inicio'                   => 'datetime',
            'fin'                      => 'datetime',
            'propuesta_inicio'         => 'datetime',
            'propuesta_fin'            => 'datetime',
            'hora_real_inicio'         => 'datetime',
            'hora_real_fin'            => 'datetime',
            'llamada_at'               => 'datetime',
            'recordatorio_24h_enviado' => 'boolean',
            'recordatorio_2h_enviado'  => 'boolean',
        ];
    }

    public function evento()
    {
        return $this->belongsTo(Evento::class, 'edicion_id');
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
