<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('publicidades', function (Blueprint $table) {
            $table->id();
            $table->string('titulo');
            $table->string('imagen');
            $table->string('enlace')->nullable();
            $table->boolean('abre_en_nueva_pestana')->default(true);
            $table->datetime('fecha_inicio');
            $table->datetime('fecha_fin');
            $table->boolean('activa')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('publicidades');
    }
};
