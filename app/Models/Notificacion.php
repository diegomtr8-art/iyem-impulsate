<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Notificacion extends Model
{
    protected $table = 'notificaciones';

    protected $fillable = ['user_id', 'tipo', 'titulo', 'mensaje', 'leida', 'cita_id'];

    protected function casts(): array
    {
        return ['leida' => 'boolean'];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function cita()
    {
        return $this->belongsTo(Cita::class);
    }

    /** Helper para crear notificaciones rápido */
    public static function crear(int $userId, string $tipo, string $titulo, string $mensaje, ?int $citaId = null): self
    {
        return static::create([
            'user_id' => $userId,
            'tipo'    => $tipo,
            'titulo'  => $titulo,
            'mensaje' => $mensaje,
            'cita_id' => $citaId,
        ]);
    }
}
