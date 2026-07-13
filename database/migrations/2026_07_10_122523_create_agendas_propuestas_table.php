<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('agendas_propuestas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evento_id')->constrained('eventos')->cascadeOnDelete();
            $table->foreignId('restaurantero_id')->constrained('restauranteros')->cascadeOnDelete();
            $table->foreignId('admin_id')->constrained('users')->cascadeOnDelete();
            $table->string('estado', 20)->default('pendiente'); // pendiente | aceptada | rechazada
            $table->string('token', 80)->unique()->nullable();
            $table->timestamp('enviada_at')->nullable();
            $table->timestamp('respondida_at')->nullable();
            $table->timestamps();

            $table->index(['evento_id', 'restaurantero_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('agendas_propuestas');
    }
};
