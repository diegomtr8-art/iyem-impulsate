<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('eventos', function (Blueprint $table) {
            $table->datetime('fecha_aceptacion_solicitudes')
                  ->nullable()
                  ->after('tipo_evento')
                  ->comment('Bazar: fecha desde la que se aceptan solicitudes de expositores');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('eventos', function (Blueprint $table) {
            $table->dropColumn('fecha_aceptacion_solicitudes');
        });
    }
};
