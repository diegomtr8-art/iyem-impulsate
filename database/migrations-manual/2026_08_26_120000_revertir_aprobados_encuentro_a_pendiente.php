<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Revierte a 'pendiente' las inscripciones aprobadas automáticamente en los
     * encuentros de negocios ACTIVOS, para que el admin decida quién entra.
     *
     * NO toca:
     *   - tipo='expositor'            (flujo del bazar)
     *   - tipo_evento='bazar_exposicion'
     *   - eventos ya finalizados o archivados (histórico intacto)
     *   - la tabla citas
     *   - restauranteros.aprobado / activo  (el directorio sigue igual)
     */
    public function up(): void
    {
        $eventos = DB::table('eventos')
            ->where('tipo_evento', 'encuentro_negocios')
            ->where('activa', 1)
            ->where(function ($q) {
                $q->whereNull('fecha_hora_fin')
                  ->orWhere('fecha_hora_fin', '>=', now());
            })
            ->pluck('id');

        if ($eventos->isEmpty()) {
            return;
        }

        $afectados = DB::table('evento_usuario')
            ->whereIn('evento_id', $eventos)
            ->whereIn('tipo', ['comprador', 'proveedor'])   // nunca 'expositor'
            ->where('estado', 'aprobado')
            ->update([
                'estado'         => 'pendiente',
                'respondido_at'  => null,
                'motivo_rechazo' => null,
                'updated_at'     => now(),
            ]);

        \Log::info("[revertir_aprobados_encuentro] {$afectados} inscripciones pasaron a pendiente.");
    }

    /**
     * Irreversible por diseño: no guardamos qué filas estaban aprobadas antes,
     * así que un rollback automático re-aprobaría a gente que quizá el admin
     * ya rechazó. Si hay que deshacerlo, se hace desde el panel de Solicitudes
     * con "Aprobar todos", o restaurando el respaldo.
     */
    public function down(): void
    {
        // no-op intencional
    }
};
