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

    protected $appends = ['imagen_url', 'imagen_carrusel_url'];

    protected $fillable = [
        'nombre',
        'sector_economico',
        'descripcion',
        'convocatoria_url',
        'imagen',
        'imagen_carrusel',
        'fecha_inicio',
        'fecha_corte',
        'activa',
        'tv_token',
        'tipo_evento',
        'fecha_aceptacion_solicitudes',
        'max_espacios',
        'con_criterios_evaluacion',
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

    public function getImagenCarruselUrlAttribute(): ?string
    {
        return $this->imagen_carrusel ? Storage::disk('public')->url($this->imagen_carrusel) : null;
    }

    protected function casts(): array
    {
        return [
            'activa'                         => 'boolean',
            'con_criterios_evaluacion'       => 'boolean',
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
            'fecha_aceptacion_solicitudes'   => 'datetime',
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

    /**
     * Consulta base de eventos activos y vigentes.
     *
     * Un evento está activo desde que el admin lo activa hasta que llega fecha_hora_fin.
     * No se bloquea por fecha_hora_inicio: ese campo marca cuándo ocurre físicamente el
     * evento, no cuándo abren las inscripciones. Los controladores manejan sus propias
     * ventanas temporales (agendado, inscripciones, etc.).
     *
     * El orden es determinista (el más próximo primero, y los que no tienen fecha al
     * final) porque desde que se admiten varios eventos activos a la vez, un `first()`
     * sin orden devolvería un evento arbitrario.
     */
    /** Devuelve el token de TV, generandolo si aun no existe. */
    public function tokenTv(): string
    {
        if (!$this->tv_token) {
            $this->forceFill(['tv_token' => \Illuminate\Support\Str::random(48)])->saveQuietly();
        }
        return $this->tv_token;
    }

    /** Rota el token: invalida cualquier pantalla abierta. */
    public function rotarTokenTv(): string
    {
        $this->forceFill(['tv_token' => \Illuminate\Support\Str::random(48)])->saveQuietly();
        return $this->tv_token;
    }

    public static function queryActivos()
    {
        return static::where('activa', true)
            ->where(function ($q) {
                $q->whereNull('fecha_hora_fin')
                  ->orWhere('fecha_hora_fin', '>=', now());
            })
            ->orderByRaw('fecha_hora_inicio IS NULL')
            ->orderBy('fecha_hora_inicio')
            ->orderBy('id');
    }

    /**
     * Todos los eventos activos y vigentes. Puede haber varios simultáneos.
     */
    public static function activos()
    {
        return static::queryActivos()->get();
    }

    /**
     * El evento activo principal: el más próximo a ocurrir.
     *
     * Se mantiene para el flujo público y para los puntos que operan sobre un solo
     * evento. Con varios activos devuelve el más próximo, nunca uno arbitrario.
     */
    public static function activo(): ?self
    {
        return static::queryActivos()->first();
    }

    /**
     * Evento sobre el que está operando el admin en las pantallas de gestión
     * (citas, agenda, exportaciones).
     *
     * Respeta el evento elegido con el selector siempre que siga activo; si no hay
     * elección o la guardada ya no es válida, cae al evento activo más próximo.
     */
    public static function contextoAdmin(): ?self
    {
        $id = session('admin_evento_id');

        if ($id) {
            $elegido = static::queryActivos()->where('id', $id)->first();
            if ($elegido) {
                return $elegido;
            }
            session()->forget('admin_evento_id');
        }

        return static::activo();
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

    public function criterios()
    {
        return $this->hasMany(EventoCriterio::class)->orderBy('orden');
    }

    public function evaluaciones()
    {
        return $this->hasMany(EventoEvaluacion::class);
    }

    public function esBazar(): bool
    {
        return $this->tipo_evento === 'bazar_exposicion';
    }

    public function espaciosDisponibles(): int
    {
        if (!$this->max_espacios) return 0;
        $seleccionados = DB::table('evento_usuario')
            ->where('evento_id', $this->id)
            ->where('seleccionado', true)
            ->count();
        return max(0, $this->max_espacios - $seleccionados);
    }

    /**
     * Registra al proveedor en el evento sobre el que opera el admin.
     *
     * Con varios eventos activos ya no existe "el" evento al que inscribir por
     * defecto, así que se usa el evento del selector del panel (contextoAdmin).
     */
    public static function registrarProveedorEnEventoActivo(int $userId, bool $aprobadoAutomatico = false): void
    {
        self::registrarProveedorEnEvento($userId, self::contextoAdmin(), $aprobadoAutomatico);
    }

    public static function registrarProveedorEnEvento(int $userId, ?self $evento, bool $aprobadoAutomatico = false): void
    {
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

    /**
     * Segundos restantes hasta que abra el registro de solicitudes de bazar.
     * Retorna null si ya abrió o si no hay fecha configurada.
     */
    public function segundosHastaSolicitudes(): ?int
    {
        if (!$this->fecha_aceptacion_solicitudes) return null;
        $diff = now()->diffInSeconds($this->fecha_aceptacion_solicitudes, false);
        return $diff > 0 ? $diff : null;
    }

    /**
     * ¿Está abierto el registro de solicitudes de bazar ahora mismo?
     */
    public function registroSolicitudesAbierto(): bool
    {
        if (!$this->fecha_aceptacion_solicitudes) return true; // sin fecha = siempre abierto
        return now()->gte($this->fecha_aceptacion_solicitudes);
    }
}
