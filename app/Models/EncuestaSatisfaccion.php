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
    ];

    protected function casts(): array
    {
        return [
            'completada_at' => 'datetime',
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

    public function respuestas()
    {
        return $this->hasMany(EncuestaRespuesta::class);
    }

    public function completada(): bool
    {
        return $this->completada_at !== null;
    }
}
