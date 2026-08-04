<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('encuesta_plantillas', function (Blueprint $table) {
            $table->string('segmento')->default('todos')->after('activa');
        });

        Schema::table('encuestas_satisfaccion', function (Blueprint $table) {
            $table->string('segmento')->nullable()->after('tipo');
        });
    }

    public function down(): void
    {
        Schema::table('encuestas_satisfaccion', function (Blueprint $table) {
            $table->dropColumn('segmento');
        });

        Schema::table('encuesta_plantillas', function (Blueprint $table) {
            $table->dropColumn('segmento');
        });
    }
};
