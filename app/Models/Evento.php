<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class Evento extends Model
{
    use HasFactory;

    protected $table = 'eventos';

    protected $appends = ['imagen_url'];

    protected $fillable = [
        'nombre',
        'sector_economico',
        'descripcion',
        'imagen',
        'fecha_inicio',
        'fecha_corte',
        'activa',
        'fecha_hora_inicio',
        'fecha_hora_fin',
        'fecha_hora_inicio_proveedores',
        'fecha_hora_fin_proveedores',
        'fecha_hora_inicio_compradores',
        'fecha_hora_fin_compradores',
        'max_citas_por_comprador',
        'tiempo_entre_citas_minutos',
        // Legacy agenda fields (se mantienen por compatibilidad)
        'fecha_inicio_agenda',
        'fecha_fin_agenda',
    ];

    public function getImagenUrlAttribute(): ?string
    {
        return $this->imagen ? Storage::disk('public')->url($this->imagen) : null;
    }

    protected function casts(): array
    {
        return [
            'activa'                         => 'boolean',
            'fecha_inicio'                   => 'date',
            'fecha_corte'                    => 'date',
            'fecha_inicio_agenda'            => 'date',
            'fecha_fin_agenda'               => 'date',
            'fecha_hora_inicio'              => 'datetime',
            'fecha_hora_fin'                 => 'datetime',
            'fecha_hora_inicio_proveedores'  => 'datetime',
            'fecha_hora_fin_proveedores'     => 'datetime',
            'fecha_hora_inicio_compradores'  => 'datetime',
            'fecha_hora_fin_compradores'     => 'datetime',
        ];
    }

    public function registroProveedoresAbierto(): bool
    {
        $inicio = $this->fecha_hora_inicio_proveedores;
        $fin    = $this->fecha_hora_fin_proveedores;
        if ($inicio && now()->lt($inicio)) return false;
        if ($fin && now()->gt($fin)) return false;
        return true;
    }

    public function registroCompradoresAbierto(): bool
    {
        $inicio = $this->fecha_hora_inicio_compradores;
        $fin    = $this->fecha_hora_fin_compradores ?? $this->fecha_hora_fin;
        if ($inicio && now()->lt($inicio)) return false;
        if ($fin && now()->gt($fin)) return false;
        return true;
    }

    public function segundosHastaProveedores(): ?int
    {
        if (!$this->fecha_hora_inicio_proveedores) return null;
        $diff = now()->diffInSeconds($this->fecha_hora_inicio_proveedores, false);
        return $diff > 0 ? $diff : null;
    }

    public function segundosHastaCompradores(): ?int
    {
        if (!$this->fecha_hora_inicio_compradores) return null;
        $diff = now()->diffInSeconds($this->fecha_hora_inicio_compradores, false);
        return $diff > 0 ? $diff : null;
    }

    public static function activo(): ?self
    {
        // Un evento está activo desde que el admin lo activa hasta que llega fecha_hora_fin.
        // No se bloquea por fecha_hora_inicio: ese campo marca cuándo ocurre físicamente el
        // evento, no cuándo abren las inscripciones. Los controladores manejan sus propias
        // ventanas temporales (agendado, inscripciones, etc.).
        return static::where('activa', true)
            ->where(function ($q) {
                $q->whereNull('fecha_hora_fin')
                  ->orWhere('fecha_hora_fin', '>=', now());
            })
            ->first();
    }

    public function citas()
    {
        return $this->hasMany(Cita::class, 'edicion_id');
    }

    public function restauranteros()
    {
        return $this->hasMany(Restaurantero::class, 'edicion_id');
    }

    public function compradores()
    {
        return $this->belongsToMany(User::class, 'evento_usuario')
                    ->wherePivot('tipo', 'comprador')
                    ->withTimestamps();
    }

    public function proveedores()
    {
        return $this->belongsToMany(User::class, 'evento_usuario')
                    ->wherePivot('tipo', 'proveedor')
                    ->withTimestamps();
    }

    public static function registrarProveedorEnEventoActivo(int $userId, bool $aprobadoAutomatico = false): void
    {
        $evento = self::activo();
        if (!$evento) return;

        $existe = DB::table('evento_usuario')
            ->where('evento_id', $evento->id)
            ->where('user_id', $userId)
            ->where('tipo', 'proveedor')
            ->exists();

        if ($existe) return;

        DB::table('evento_usuario')->insert([
            'evento_id'     => $evento->id,
            'user_id'       => $userId,
            'tipo'          => 'proveedor',
            'estado'        => $aprobadoAutomatico ? 'aprobado' : 'pendiente',
            'respondido_at' => $aprobadoAutomatico ? now() : null,
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);
    }
}
