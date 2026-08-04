<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EncuestaPlantilla extends Model
{
    protected $table = 'encuesta_plantillas';

    protected $fillable = ['nombre', 'descripcion', 'preguntas', 'activa', 'segmento'];

    protected function casts(): array
    {
        return [
            'preguntas' => 'array',
            'activa'    => 'boolean',
        ];
    }

    public function encuestasEnviadas()
    {
        return $this->hasMany(EncuestaSatisfaccion::class);
    }
}
