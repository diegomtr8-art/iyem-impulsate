<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evento_usuario', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evento_id')->constrained('eventos')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->string('tipo', 20); // 'comprador' | 'proveedor'
            $table->timestamps();

            $table->unique(['evento_id', 'user_id', 'tipo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evento_usuario');
    }
};
