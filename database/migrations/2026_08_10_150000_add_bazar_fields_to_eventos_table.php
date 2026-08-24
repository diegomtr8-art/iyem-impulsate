<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('eventos', function (Blueprint $table) {
            $table->string('tipo_evento', 30)->default('encuentro_negocios')->after('activa');
            // Valores: 'encuentro_negocios' | 'bazar_exposicion'

            $table->unsignedInteger('max_espacios')->nullable()->after('tipo_evento');
            // Solo aplica cuando tipo_evento = 'bazar_exposicion'

            $table->boolean('con_criterios_evaluacion')->default(false)->after('max_espacios');
        });
    }

    public function down(): void
    {
        Schema::table('eventos', function (Blueprint $table) {
            $table->dropColumn(['tipo_evento', 'max_espacios', 'con_criterios_evaluacion']);
        });
    }
};
