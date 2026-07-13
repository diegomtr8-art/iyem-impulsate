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
        Schema::create('agenda_propuesta_citas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agenda_propuesta_id')->constrained('agendas_propuestas')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->dateTime('slot_inicio');
            $table->dateTime('slot_fin');
            $table->timestamps();

            $table->index(['agenda_propuesta_id']);
            $table->index(['user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('agenda_propuesta_citas');
    }
};
