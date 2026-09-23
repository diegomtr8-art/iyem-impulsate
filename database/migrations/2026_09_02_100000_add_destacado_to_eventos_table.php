<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Marca cual es el evento principal de cara al publico.
     *
     * Antes el "principal" se derivaba de fecha_hora_inicio (el mas proximo),
     * lo que mezcla "cuando ocurre" con "cual quiero destacar". Con varios
     * encuentros activos a la vez hace falta decirlo explicitamente.
     */
    public function up(): void
    {
        if (Schema::hasColumn('eventos', 'destacado')) {
            return;
        }

        Schema::table('eventos', function (Blueprint $table) {
            $table->boolean('destacado')->default(false)->after('activa');
            $table->index(['activa', 'destacado'], 'eventos_activa_destacado_idx');
        });
    }

    public function down(): void
    {
        Schema::table('eventos', function (Blueprint $table) {
            $table->dropIndex('eventos_activa_destacado_idx');
            $table->dropColumn('destacado');
        });
    }
};
