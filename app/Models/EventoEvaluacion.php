<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EventoEvaluacion extends Model
{
    protected $table = 'evento_evaluaciones';

    protected $fillable = ['evento_id', 'user_id', 'criterio_id', 'puntaje'];

    protected function casts(): array
    {
        return ['puntaje' => 'decimal:2'];
    }

    public function criterio()
    {
        return $this->belongsTo(EventoCriterio::class, 'criterio_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
