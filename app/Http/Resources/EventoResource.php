<?php

namespace App\Http\Resources;

use App\Services\RegistroEventoService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Evento para la app, con las decisiones YA tomadas por el servidor
 * (puede_registrarse, motivo_bloqueo, accion): la app no reimplementa las
 * ventanas de registro.
 *
 * Para listas, el controlador precarga los registros del usuario con
 * setRelation('mis_registros', ...) y se evita una consulta por evento.
 */
class EventoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user      = $request->user();
        $servicio  = app(RegistroEventoService::class);
        $registros = $this->misRegistros($user?->id);

        $miRegistro = [];
        foreach (RegistroEventoService::TIPOS as $tipo) {
            $fila = $registros->firstWhere('tipo', $tipo);
            $miRegistro[$tipo] = $fila ? [
                'estado'         => $fila->estado,
                'motivo_rechazo' => $fila->motivo_rechazo ?? null,
                'registrado_at'  => $fila->created_at ? \Carbon\Carbon::parse($fila->created_at)->toIso8601String() : null,
            ] : null;
        }

        $tipo    = $user ? $servicio->tipoParaUsuario($user, $this->resource) : null;
        $estado  = $tipo ? ($miRegistro[$tipo]['estado'] ?? null) : null;
        $motivo  = $tipo ? $servicio->motivoBloqueo($user, $this->resource, $tipo) : 'Tu cuenta no puede registrarse a eventos.';
        $puede   = $tipo && !$motivo && !in_array($estado, ['pendiente', 'aprobado']);

        return [
            'id'                => $this->id,
            'nombre'            => $this->nombre,
            'descripcion'       => $this->descripcion,
            'sector_economico'  => $this->sector_economico,
            'tipo_evento'       => $this->tipo_evento ?? 'encuentro_negocios',
            'imagen_url'        => $this->imagen_url,
            'convocatoria_url'  => $this->convocatoria_url,
            'fecha_hora_inicio' => $this->fecha_hora_inicio?->toIso8601String(),
            'fecha_hora_fin'    => $this->fecha_hora_fin?->toIso8601String(),
            'estado'            => $this->estadoPublico(),
            'destacado'         => (bool) $this->destacado,
            'fases'             => $this->fases(),
            'mi_registro'       => $miRegistro,
            'tipo_registro'     => $tipo,
            'puede_registrarse' => $puede,
            'motivo_bloqueo'    => in_array($estado, ['pendiente', 'aprobado']) ? null : $motivo,
            'accion'            => $this->accion($tipo, $estado, $puede),
        ];
    }

    private function misRegistros(?int $userId): Collection
    {
        if ($this->resource->relationLoaded('mis_registros')) {
            return collect($this->resource->getRelation('mis_registros'));
        }
        if (!$userId) {
            return collect();
        }

        return DB::table('evento_usuario')
            ->where('evento_id', $this->id)
            ->where('user_id', $userId)
            ->get();
    }

    /** Misma logica que la insignia del sidebar web (EventosSidebar.vue). */
    private function estadoPublico(): string
    {
        if ($this->fecha_hora_fin && now()->gt($this->fecha_hora_fin)) {
            return 'finalizado';
        }
        if ($this->activa) {
            return 'activo';
        }
        if ($this->fecha_hora_inicio && now()->lt($this->fecha_hora_inicio)) {
            return 'proximamente';
        }

        return 'finalizado';
    }

    /** Misma logica que los botones del sidebar web. */
    private function accion(?string $tipo, ?string $estado, bool $puede): string
    {
        if ($estado === 'aprobado') {
            return $tipo === 'expositor' ? 'aprobado' : 'ver_proveedores';
        }
        if ($estado === 'pendiente') {
            return 'en_revision';
        }
        if (!$puede) {
            return 'bloqueado';
        }

        return $estado === 'rechazado' ? 'volver_a_postular' : 'registrate';
    }

    private function fases(): array
    {
        $fase = fn (string $clave, string $titulo, $inicio, $fin) => [
            'clave'  => $clave,
            'titulo' => $titulo,
            'inicio' => $inicio?->toIso8601String(),
            'fin'    => $fin?->toIso8601String(),
            'estado' => match (true) {
                $inicio && now()->lt($inicio) => 'pendiente',
                $fin && now()->gt($fin)       => 'terminada',
                default                       => 'en_curso',
            },
        ];

        if ($this->tipo_evento === 'bazar_exposicion') {
            return [
                $fase('solicitudes', 'Solicitudes', $this->fecha_aceptacion_solicitudes, $this->fecha_hora_inicio),
                $fase('evento', 'Evento', $this->fecha_hora_inicio, $this->fecha_hora_fin),
            ];
        }

        return [
            $fase('registro_proveedores', 'Registro de proveedores', $this->fecha_hora_inicio_proveedores, $this->fecha_hora_fin_proveedores),
            $fase('registro_compradores', 'Registro de compradores', $this->fecha_hora_inicio_compradores, $this->fecha_hora_fin_compradores ?? $this->fecha_hora_fin),
            $fase('evento', 'Evento', $this->fecha_hora_inicio, $this->fecha_hora_fin),
        ];
    }
}
