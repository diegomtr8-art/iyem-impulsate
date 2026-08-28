<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    /**
     * Rellena agenda_propuesta_citas.cita_id en las agendas ya ACEPTADAS antes
     * de que existiera esa columna, emparejando por proveedor + comprador +
     * hora de inicio + evento. Sin esto, AgendaController::destroy() no puede
     * cancelar las citas de una propuesta vieja y las deja huerfanas.
     */
    public function up(): void
    {
        $filas = DB::table('agenda_propuesta_citas as apc')
            ->join('agendas_propuestas as ap', 'ap.id', '=', 'apc.agenda_propuesta_id')
            ->whereNull('apc.cita_id')
            ->where('ap.estado', 'aceptada')
            ->select(
                'apc.id',
                'apc.restaurantero_id',
                'apc.slot_inicio',
                'ap.user_id',
                'ap.evento_id'
            )
            ->get();

        $emparejadas = 0;
        $ambiguas    = 0;
        $sinCita     = 0;

        foreach ($filas as $f) {
            $candidatas = DB::table('citas')
                ->where('restaurantero_id', $f->restaurantero_id)
                ->where('cliente_id', $f->user_id)
                ->where('edicion_id', $f->evento_id)
                ->where('inicio', $f->slot_inicio)
                ->pluck('id');

            if ($candidatas->count() === 1) {
                DB::table('agenda_propuesta_citas')
                    ->where('id', $f->id)
                    ->update(['cita_id' => $candidatas->first()]);
                $emparejadas++;
            } elseif ($candidatas->count() > 1) {
                // Mas de una cita para el mismo slot: no adivinamos, se deja null.
                $ambiguas++;
                Log::warning("[backfill_cita_id] {$candidatas->count()} citas para apc={$f->id}. Se deja sin vincular.");
            } else {
                $sinCita++;
            }
        }

        Log::info("[backfill_cita_id] {$emparejadas} vinculadas, {$ambiguas} ambiguas, {$sinCita} sin cita.");
    }

    public function down(): void
    {
        // No se revierte: dejar cita_id en null no aporta nada y perderia
        // el vinculo recien reconstruido.
    }
};
