<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('eventos', function (Blueprint $table) {
            $table->string('convocatoria_url', 500)->nullable()->after('descripcion');
            $table->string('imagen_carrusel')->nullable()->after('imagen');
        });
    }

    public function down(): void
    {
        Schema::table('eventos', function (Blueprint $table) {
            $table->dropColumn(['convocatoria_url', 'imagen_carrusel']);
        });
    }
};
