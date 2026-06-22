<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('encuestas_satisfaccion', function (Blueprint $table) {
            $table->boolean('es_prueba')->default(false)->after('encuesta_plantilla_id');
            $table->string('email_prueba')->nullable()->after('es_prueba');
        });

        // Hacer nullable evento_id y user_id para encuestas de prueba sin participante real.
        DB::statement('ALTER TABLE encuestas_satisfaccion MODIFY evento_id BIGINT UNSIGNED NULL');
        DB::statement('ALTER TABLE encuestas_satisfaccion MODIFY user_id BIGINT UNSIGNED NULL');
    }

    public function down(): void
    {
        Schema::table('encuestas_satisfaccion', function (Blueprint $table) {
            $table->dropColumn(['es_prueba', 'email_prueba']);
        });
    }
};
