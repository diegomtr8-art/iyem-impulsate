<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EventoCriterio extends Model
{
    protected $table = 'evento_criterios';

    protected $fillable = ['evento_id', 'nombre', 'porcentaje', 'orden'];

    protected function casts(): array
    {
        return ['porcentaje' => 'decimal:2'];
    }

    public function evento()
    {
        return $this->belongsTo(Evento::class);
    }

    public function evaluaciones()
    {
        return $this->hasMany(EventoEvaluacion::class, 'criterio_id');
    }
}
