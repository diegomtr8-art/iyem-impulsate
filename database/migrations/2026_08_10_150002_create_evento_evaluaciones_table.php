<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evento_evaluaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evento_id')->constrained('eventos')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('criterio_id')->constrained('evento_criterios')->cascadeOnDelete();
            $table->decimal('puntaje', 5, 2); // 0 - 100
            $table->timestamps();
            $table->unique(['evento_id', 'user_id', 'criterio_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evento_evaluaciones');
    }
};
