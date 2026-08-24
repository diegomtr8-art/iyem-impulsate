<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('evento_usuario', function (Blueprint $table) {
            $table->decimal('puntaje_total', 5, 2)->nullable()->after('respondido_at');
            $table->boolean('seleccionado')->default(false)->after('puntaje_total');
            $table->string('token_evaluacion', 60)->nullable()->unique()->after('seleccionado');
            $table->boolean('correo_aprobacion_enviado')->default(false)->after('token_evaluacion');
            $table->boolean('correo_rechazo_enviado')->default(false)->after('correo_aprobacion_enviado');
        });
    }

    public function down(): void
    {
        Schema::table('evento_usuario', function (Blueprint $table) {
            $table->dropColumn([
                'puntaje_total',
                'seleccionado',
                'token_evaluacion',
                'correo_aprobacion_enviado',
                'correo_rechazo_enviado',
            ]);
        });
    }
};
