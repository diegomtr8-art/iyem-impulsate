<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('restauranteros', function (Blueprint $table) {
            $table->string('foto_path')->nullable()->after('logo_path');
            $table->json('productos_top')->nullable()->after('descripcion');
            $table->json('categorias_json')->nullable()->after('productos_top');
            $table->string('rfc', 13)->nullable()->after('categorias_json');
            $table->string('sitio_web')->nullable()->after('rfc');
            $table->json('redes_sociales')->nullable()->after('sitio_web');
            $table->boolean('aprobado')->default(false)->after('activo');
            $table->boolean('rechazado')->default(false)->after('aprobado');
            $table->text('motivo_rechazo')->nullable()->after('rechazado');
            $table->timestamp('solicitado_aprobacion_at')->nullable()->after('motivo_rechazo');
        });

        // Restauranteros existentes (ya activos antes de este flujo) se aprueban automáticamente
        \DB::table('restauranteros')->where('activo', true)->update(['aprobado' => true]);
    }

    public function down(): void
    {
        Schema::table('restauranteros', function (Blueprint $table) {
            $table->dropColumn([
                'foto_path', 'productos_top', 'categorias_json', 'rfc',
                'sitio_web', 'redes_sociales', 'aprobado', 'rechazado',
                'motivo_rechazo', 'solicitado_aprobacion_at',
            ]);
        });
    }
};
