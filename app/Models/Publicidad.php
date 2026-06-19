<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Publicidad extends Model
{
    protected $table = 'publicidades';

    protected $fillable = [
        'titulo', 'imagen', 'enlace', 'abre_en_nueva_pestana',
        'fecha_inicio', 'fecha_fin', 'activa',
    ];

    protected $appends = ['imagen_url', 'estado_label'];

    protected function casts(): array
    {
        return [
            'abre_en_nueva_pestana' => 'boolean',
            'activa'                => 'boolean',
            'fecha_inicio'          => 'datetime',
            'fecha_fin'             => 'datetime',
        ];
    }

    // Una sola publicidad vigente puede mostrarse a la vez.
    // Si hay solapamiento de fechas, se muestra la creada más recientemente.
    public function scopeVigente(Builder $query): Builder
    {
        $now = now();
        return $query->where('activa', true)
            ->where('fecha_inicio', '<=', $now)
            ->where('fecha_fin', '>=', $now)
            ->orderByDesc('created_at');
    }

    public function getImagenUrlAttribute(): ?string
    {
        return $this->imagen
            ? Storage::disk('public')->url($this->imagen)
            : null;
    }

    public function getEstadoLabelAttribute(): string
    {
        if (!$this->activa) return 'Inactiva';
        $now = now();
        if ($now < $this->fecha_inicio) return 'Programada';
        if ($now > $this->fecha_fin) return 'Expirada';
        return 'Vigente';
    }
}
