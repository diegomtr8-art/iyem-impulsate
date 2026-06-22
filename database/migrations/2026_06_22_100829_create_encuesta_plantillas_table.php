<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('encuesta_plantillas', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->text('descripcion')->nullable();
            $table->json('preguntas');
            $table->boolean('activa')->default(false);
            $table->timestamps();
        });

        Schema::table('encuestas_satisfaccion', function (Blueprint $table) {
            $table->foreignId('encuesta_plantilla_id')->nullable()
                  ->after('tipo')->constrained('encuesta_plantillas')->nullOnDelete();
        });

        Schema::table('encuesta_respuestas', function (Blueprint $table) {
            $table->string('tipo')->nullable()->after('pregunta');
        });
    }

    public function down(): void
    {
        Schema::table('encuesta_respuestas', function (Blueprint $table) {
            $table->dropColumn('tipo');
        });
        Schema::table('encuestas_satisfaccion', function (Blueprint $table) {
            $table->dropConstrainedForeignId('encuesta_plantilla_id');
        });
        Schema::dropIfExists('encuesta_plantillas');
    }
};
