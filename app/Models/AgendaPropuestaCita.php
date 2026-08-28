<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgendaPropuestaCita extends Model
{
    protected $fillable = [
        'agenda_propuesta_id', 'restaurantero_id', 'slot_inicio', 'slot_fin', 'cita_id',
    ];

    protected function casts(): array
    {
        return [
            'slot_inicio' => 'datetime',
            'slot_fin'    => 'datetime',
        ];
    }

    public function agenda(): BelongsTo
    {
        return $this->belongsTo(AgendaPropuesta::class, 'agenda_propuesta_id');
    }

    // El PROVEEDOR asignado a este slot
    public function proveedor(): BelongsTo
    {
        return $this->belongsTo(Restaurantero::class, 'restaurantero_id');
    }

    // La cita real creada a partir de este renglon (si el comprador acepto)
    public function cita(): BelongsTo
    {
        return $this->belongsTo(Cita::class, 'cita_id');
    }
}
