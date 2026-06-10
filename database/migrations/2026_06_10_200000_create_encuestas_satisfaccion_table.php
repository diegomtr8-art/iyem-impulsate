<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('encuestas_satisfaccion', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evento_id')->constrained('eventos')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->enum('tipo', ['comprador', 'proveedor']);
            $table->string('token', 60)->unique();
            $table->timestamp('completada_at')->nullable();
            $table->timestamps();

            $table->unique(['evento_id', 'user_id', 'tipo']);
        });

        Schema::create('encuesta_respuestas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('encuesta_satisfaccion_id')
                  ->constrained('encuestas_satisfaccion')
                  ->onDelete('cascade');
            $table->string('pregunta');
            $table->text('respuesta');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('encuesta_respuestas');
        Schema::dropIfExists('encuestas_satisfaccion');
    }
};
