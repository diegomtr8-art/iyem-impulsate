<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('eventos', function (Blueprint $table) {
            $table->dateTime('fecha_hora_fin_proveedores')
                  ->nullable()->after('fecha_hora_inicio_proveedores');
            $table->dateTime('fecha_hora_fin_compradores')
                  ->nullable()->after('fecha_hora_inicio_compradores');
        });
    }

    public function down(): void
    {
        Schema::table('eventos', function (Blueprint $table) {
            $table->dropColumn(['fecha_hora_fin_proveedores', 'fecha_hora_fin_compradores']);
        });
    }
};
