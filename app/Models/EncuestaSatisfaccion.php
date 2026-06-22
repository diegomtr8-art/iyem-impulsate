<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EncuestaSatisfaccion extends Model
{
    protected $table = 'encuestas_satisfaccion';

    protected $fillable = [
        'evento_id',
        'user_id',
        'tipo',
        'token',
        'completada_at',
        'encuesta_plantilla_id',
        'es_prueba',
        'email_prueba',
    ];

    protected function casts(): array
    {
        return [
            'completada_at' => 'datetime',
            'es_prueba'     => 'boolean',
        ];
    }

    public function evento()
    {
        return $this->belongsTo(Evento::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function plantilla()
    {
        return $this->belongsTo(EncuestaPlantilla::class, 'encuesta_plantilla_id');
    }

    public function respuestas()
    {
        return $this->hasMany(EncuestaRespuesta::class);
    }

    public function completada(): bool
    {
        return $this->completada_at !== null;
    }
}
