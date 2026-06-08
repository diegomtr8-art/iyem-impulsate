<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('evento_usuario', function (Blueprint $table) {
            $table->string('estado', 20)->default('pendiente')->after('tipo');
            $table->text('motivo_rechazo')->nullable()->after('estado');
            $table->timestamp('respondido_at')->nullable()->after('motivo_rechazo');
        });

        // Registros existentes quedan aprobados para no romper lo que ya funciona
        DB::table('evento_usuario')->update(['estado' => 'aprobado']);
    }

    public function down(): void
    {
        Schema::table('evento_usuario', function (Blueprint $table) {
            $table->dropColumn(['estado', 'motivo_rechazo', 'respondido_at']);
        });
    }
};
