<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('citas', function (Blueprint $table) {
            // Improves every query that filters by appointment state
            $table->index('estado', 'citas_estado_idx');

            // Improves date-range queries used in dashboard, calendar and availability checks
            $table->index('inicio', 'citas_inicio_idx');

            // Covering index for the provider availability check (the most-executed query):
            // Cita::where('restaurantero_id')->whereNotIn('estado')->where('inicio'<fin)->where('fin'>inicio)
            $table->index(['restaurantero_id', 'inicio', 'estado'], 'citas_restaurantero_inicio_estado_idx');
        });
    }

    public function down(): void
    {
        Schema::table('citas', function (Blueprint $table) {
            $table->dropIndex('citas_estado_idx');
            $table->dropIndex('citas_inicio_idx');
            $table->dropIndex('citas_restaurantero_inicio_estado_idx');
        });
    }
};
