<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notificaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('tipo', 50)->default('info'); // info, cita_nueva, cita_aceptada, etc.
            $table->string('titulo', 200);
            $table->text('mensaje');
            $table->boolean('leida')->default(false);
            $table->foreignId('cita_id')->nullable()->constrained('citas')->onDelete('set null');
            $table->timestamps();

            $table->index(['user_id', 'leida']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notificaciones');
    }
};
