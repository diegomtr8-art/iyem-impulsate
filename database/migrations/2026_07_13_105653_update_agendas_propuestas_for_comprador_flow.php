<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Invierte el flujo de agenda: ahora la propuesta se dirige a un COMPRADOR
     * (user_id) y cada cita de la propuesta asigna un PROVEEDOR (restaurantero_id).
     *
     * El índice compuesto (evento_id, restaurantero_id) también sirve de soporte
     * a la FK de evento_id (por ser su columna izquierda), así que el nuevo índice
     * (evento_id, user_id) se crea ANTES de eliminar el viejo, o MySQL rechaza el
     * DROP INDEX con error 1553.
     */
    public function up(): void
    {
        Schema::table('agendas_propuestas', function (Blueprint $table) {
            $table->dropForeign(['restaurantero_id']);
        });
        Schema::table('agendas_propuestas', function (Blueprint $table) {
            $table->foreignId('user_id')
                  ->after('evento_id')
                  ->constrained('users')
                  ->cascadeOnDelete();
            $table->index(['evento_id', 'user_id']);
        });
        Schema::table('agendas_propuestas', function (Blueprint $table) {
            $table->dropIndex(['evento_id', 'restaurantero_id']);
            $table->dropIndex('agendas_propuestas_restaurantero_id_foreign');
        });
        Schema::table('agendas_propuestas', function (Blueprint $table) {
            $table->dropColumn('restaurantero_id');
        });

        Schema::table('agenda_propuesta_citas', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
        });
        Schema::table('agenda_propuesta_citas', function (Blueprint $table) {
            $table->dropIndex(['user_id']);
        });
        Schema::table('agenda_propuesta_citas', function (Blueprint $table) {
            $table->dropColumn('user_id');
            $table->foreignId('restaurantero_id')
                  ->after('agenda_propuesta_id')
                  ->constrained('restauranteros')
                  ->cascadeOnDelete();
            $table->index(['restaurantero_id']);
        });
    }

    public function down(): void
    {
        Schema::table('agenda_propuesta_citas', function (Blueprint $table) {
            $table->dropForeign(['restaurantero_id']);
        });
        Schema::table('agenda_propuesta_citas', function (Blueprint $table) {
            $table->dropIndex(['restaurantero_id']);
        });
        Schema::table('agenda_propuesta_citas', function (Blueprint $table) {
            $table->dropColumn('restaurantero_id');
            $table->foreignId('user_id')->after('agenda_propuesta_id')->constrained('users')->cascadeOnDelete();
            $table->index(['user_id']);
        });

        Schema::table('agendas_propuestas', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
        });
        Schema::table('agendas_propuestas', function (Blueprint $table) {
            $table->foreignId('restaurantero_id')->after('evento_id')->constrained('restauranteros')->cascadeOnDelete();
            $table->index(['evento_id', 'restaurantero_id']);
        });
        Schema::table('agendas_propuestas', function (Blueprint $table) {
            $table->dropIndex(['evento_id', 'user_id']);
            $table->dropIndex('agendas_propuestas_user_id_foreign');
        });
        Schema::table('agendas_propuestas', function (Blueprint $table) {
            $table->dropColumn('user_id');
        });
    }
};
