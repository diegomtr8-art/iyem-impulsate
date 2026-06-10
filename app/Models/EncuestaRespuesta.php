<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EncuestaRespuesta extends Model
{
    protected $table = 'encuesta_respuestas';

    protected $fillable = [
        'encuesta_satisfaccion_id',
        'pregunta',
        'respuesta',
    ];

    public function encuesta()
    {
        return $this->belongsTo(EncuestaSatisfaccion::class, 'encuesta_satisfaccion_id');
    }
}
