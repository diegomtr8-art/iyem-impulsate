<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class AgendaPropuesta extends Model
{
    protected $table = 'agendas_propuestas';

    protected $fillable = [
        'evento_id', 'user_id', 'admin_id',
        'estado', 'token', 'enviada_at', 'respondida_at',
    ];

    protected function casts(): array
    {
        return [
            'enviada_at'    => 'datetime',
            'respondida_at' => 'datetime',
        ];
    }

    public function evento(): BelongsTo
    {
        return $this->belongsTo(Evento::class);
    }

    // El COMPRADOR que recibe la propuesta
    public function comprador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    public function citas(): HasMany
    {
        return $this->hasMany(AgendaPropuestaCita::class);
    }

    public function generarToken(): string
    {
        $this->token = Str::random(64);
        $this->save();
        return $this->token;
    }

    public function esPendiente(): bool
    {
        return $this->estado === 'pendiente';
    }
}
