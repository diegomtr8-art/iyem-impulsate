<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ediciones', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->string('sector')->nullable();
            $table->text('descripcion')->nullable();
            $table->date('fecha_inicio');
            $table->date('fecha_corte')->nullable();
            $table->boolean('activa')->default(false);
            $table->timestamps();
        });

        // Crear edición inicial activa con los datos existentes
        DB::table('ediciones')->insert([
            'nombre'       => 'Edición Inicial',
            'sector'       => 'General',
            'descripcion'  => 'Edición inicial migrada automáticamente desde el sistema.',
            'fecha_inicio' => now()->toDateString(),
            'activa'       => true,
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('ediciones');
    }
};
