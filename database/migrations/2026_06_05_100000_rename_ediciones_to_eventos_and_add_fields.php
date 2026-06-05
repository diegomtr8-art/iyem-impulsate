<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Deshabilitar FK checks para poder renombrar la tabla referenciada
        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        // Eliminar las FKs que apuntan a ediciones
        Schema::table('citas', function (Blueprint $table) {
            $table->dropForeign(['edicion_id']);
        });
        Schema::table('restauranteros', function (Blueprint $table) {
            $table->dropForeign(['edicion_id']);
        });

        // Renombrar la tabla
        Schema::rename('ediciones', 'eventos');

        // Renombrar columna sector → sector_economico y agregar nuevos campos
        Schema::table('eventos', function (Blueprint $table) {
            $table->renameColumn('sector', 'sector_economico');
            $table->datetime('fecha_hora_inicio')->nullable()->after('fecha_inicio');
            $table->datetime('fecha_hora_fin')->nullable()->after('fecha_hora_inicio');
            $table->datetime('fecha_hora_inicio_proveedores')->nullable()->after('fecha_hora_fin');
            $table->datetime('fecha_hora_inicio_compradores')->nullable()->after('fecha_hora_inicio_proveedores');
            $table->integer('max_citas_por_comprador')->default(3)->after('fecha_hora_inicio_compradores');
            $table->integer('tiempo_entre_citas_minutos')->default(30)->after('max_citas_por_comprador');
        });

        // Rellenar fecha_hora_inicio con el valor de fecha_inicio para registros existentes
        DB::statement('UPDATE eventos SET fecha_hora_inicio = fecha_inicio WHERE fecha_hora_inicio IS NULL');

        // Re-agregar las FKs apuntando a la tabla renombrada
        Schema::table('citas', function (Blueprint $table) {
            $table->foreign('edicion_id')
                  ->references('id')
                  ->on('eventos')
                  ->onDelete('set null');
        });
        Schema::table('restauranteros', function (Blueprint $table) {
            $table->foreign('edicion_id')
                  ->references('id')
                  ->on('eventos')
                  ->onDelete('set null');
        });

        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    public function down(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        Schema::table('citas', function (Blueprint $table) {
            $table->dropForeign(['edicion_id']);
        });
        Schema::table('restauranteros', function (Blueprint $table) {
            $table->dropForeign(['edicion_id']);
        });

        Schema::table('eventos', function (Blueprint $table) {
            $table->dropColumn([
                'fecha_hora_inicio',
                'fecha_hora_fin',
                'fecha_hora_inicio_proveedores',
                'fecha_hora_inicio_compradores',
                'max_citas_por_comprador',
                'tiempo_entre_citas_minutos',
            ]);
            $table->renameColumn('sector_economico', 'sector');
        });

        Schema::rename('eventos', 'ediciones');

        Schema::table('citas', function (Blueprint $table) {
            $table->foreign('edicion_id')
                  ->references('id')
                  ->on('ediciones')
                  ->onDelete('set null');
        });
        Schema::table('restauranteros', function (Blueprint $table) {
            $table->foreign('edicion_id')
                  ->references('id')
                  ->on('ediciones')
                  ->onDelete('set null');
        });

        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }
};
