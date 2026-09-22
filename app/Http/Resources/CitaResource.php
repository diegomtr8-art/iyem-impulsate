<?php

namespace App\Http\Resources;

use App\Http\Resources\Concerns\UrlPublica;
use App\Services\AgendaCitaService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Cita completa para la app. Los flags puede_* los decide el servidor con las
 * mismas reglas que AgendaCitaService / CitaPolicy. El comprador solo se
 * incluye si quien mira es el proveedor de la cita o un admin.
 *
 * Precargar: restaurantero, evento (y cliente si lo ve el proveedor/admin).
 */
class CitaResource extends JsonResource
{
    use UrlPublica;

    public const ESTADOS_TEXTO = [
        'pendiente'                => 'Pendiente',
        'confirmada'               => 'Confirmada',
        'cancelada'                => 'Cancelada',
        'completada'               => 'Completada',
        'rechazada'                => 'Rechazada',
        'reagendada'               => 'Reagendada',
        'pendiente_reconfirmacion' => 'Nuevo horario por confirmar',
    ];

    public function toArray(Request $request): array
    {
        $user        = $request->user();
        $proveedor   = $this->restaurantero;
        $esComprador = $user && (int) $this->cliente_id === (int) $user->id;
        $esProveedor = $user && $proveedor && (int) $proveedor->user_id === (int) $user->id;
        $esAdmin     = $user && ($user->hasRole('admin') || $user->hasRole('super-admin'));

        $hayPropuesta = $this->estado === 'pendiente_reconfirmacion' && $this->propuesta_inicio;

        return [
            'id'                => $this->id,
            'inicio'            => $this->inicio?->toIso8601String(),
            'fin'               => $this->fin?->toIso8601String(),
            'estado'            => $this->estado,
            'estado_texto'      => self::ESTADOS_TEXTO[$this->estado] ?? ucfirst((string) $this->estado),
            'notas'             => $this->notas,
            'productos_interes' => $this->productos_interes ?: [],
            'mesa'              => $this->mesa,
            'evento'            => $this->evento ? ['id' => $this->evento->id, 'nombre' => $this->evento->nombre] : null,
            'proveedor'         => $proveedor ? [
                'id'             => $proveedor->id,
                'nombre_negocio' => $proveedor->nombre_restaurante,
                'categoria'      => $proveedor->categoria,
                'logo_url'       => self::urlPublica($proveedor->logo_path),
                'municipio'      => $proveedor->municipio,
            ] : null,
            'comprador'         => $this->when($esProveedor || $esAdmin, fn () => $this->cliente ? [
                'id'             => $this->cliente->id,
                'nombre'         => $this->cliente->name,
                'nombre_empresa' => $this->cliente->nombre_empresa,
            ] : null),
            'propuesta_reagenda' => $hayPropuesta ? [
                'inicio' => $this->propuesta_inicio->toIso8601String(),
                'fin'    => $this->propuesta_fin?->toIso8601String(),
            ] : null,
            'puede_cancelar'           => $esComprador && !in_array($this->estado, AgendaCitaService::ESTADOS_NO_CANCELABLES),
            'puede_editar_notas'       => $esComprador,
            'puede_responder_reagenda' => $esComprador && (bool) $hayPropuesta,
            'creada_at'                => $this->created_at?->toIso8601String(),
        ];
    }
}
