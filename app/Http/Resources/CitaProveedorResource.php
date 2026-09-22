<?php

namespace App\Http\Resources;

use App\Services\GestionCitasProveedorService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Cita RECIBIDA, tal como la ve el proveedor en su panel y en /proveedor/citas:
 * del comprador, lo mismo que muestra la web (nombre, correo, telefono,
 * necesidades) mas su empresa. Lista blanca: nunca documentos, CURP, RFC ni
 * rutas internas. Las acciones (puede_*) las decide GestionCitasProveedorService.
 *
 * Precargar: cliente, evento.
 */
class CitaProveedorResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $hayPropuesta = $this->estado === 'pendiente_reconfirmacion' && $this->propuesta_inicio;
        $cliente      = $this->cliente;

        return array_merge([
            'id'                => $this->id,
            'inicio'            => $this->inicio?->toIso8601String(),
            'fin'               => $this->fin?->toIso8601String(),
            'estado'            => $this->estado,
            'estado_texto'      => CitaResource::ESTADOS_TEXTO[$this->estado] ?? ucfirst((string) $this->estado),
            'notas'             => $this->notas,
            'productos_interes' => $this->productos_interes ?: [],
            'mesa'              => $this->mesa,
            'evento'            => $this->evento ? ['id' => $this->evento->id, 'nombre' => $this->evento->nombre] : null,
            'comprador'         => $cliente ? [
                'id'             => $cliente->id,
                'nombre'         => $cliente->name,
                'email'          => $cliente->email,
                'telefono'       => $cliente->telefono,
                'nombre_empresa' => $cliente->nombre_empresa,
                'necesidades'    => $cliente->necesidades,
            ] : null,
            // Propuesta enviada por el proveedor que el comprador aun no responde.
            'propuesta_reagenda' => $hayPropuesta ? [
                'inicio' => $this->propuesta_inicio->toIso8601String(),
                'fin'    => $this->propuesta_fin?->toIso8601String(),
            ] : null,
        ], app(GestionCitasProveedorService::class)->acciones($this->resource), [
            'creada_at' => $this->created_at?->toIso8601String(),
        ]);
    }
}
