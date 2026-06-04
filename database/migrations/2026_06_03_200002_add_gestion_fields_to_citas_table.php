<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('citas', function (Blueprint $table) {
            // Ampliar ENUM con nuevos estados para gestión del proveedor
            \DB::statement("ALTER TABLE citas MODIFY COLUMN estado ENUM('pendiente','confirmada','cancelada','completada','rechazada','reagendada','pendiente_reconfirmacion') DEFAULT 'pendiente'");

            $table->datetime('propuesta_inicio')->nullable()->after('fin');
            $table->datetime('propuesta_fin')->nullable()->after('propuesta_inicio');
            $table->string('token_confirmacion', 64)->nullable()->after('propuesta_fin');

            // Columnas de recordatorio (Feature 5)
            $table->boolean('recordatorio_24h_enviado')->default(false)->after('token_confirmacion');
            $table->boolean('recordatorio_2h_enviado')->default(false)->after('recordatorio_24h_enviado');
        });
    }

    public function down(): void
    {
        Schema::table('citas', function (Blueprint $table) {
            $table->dropColumn(['propuesta_inicio', 'propuesta_fin', 'token_confirmacion', 'recordatorio_24h_enviado', 'recordatorio_2h_enviado']);
        });
        \DB::statement("ALTER TABLE citas MODIFY COLUMN estado ENUM('pendiente','confirmada','cancelada','completada') DEFAULT 'pendiente'");
    }
};
