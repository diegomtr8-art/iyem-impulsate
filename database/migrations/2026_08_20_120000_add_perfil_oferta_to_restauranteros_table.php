<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('restauranteros', function (Blueprint $table) {
            // Tipo de oferta
            $table->enum('tipo_oferta', ['producto', 'servicio', 'ambos'])
                  ->default('producto')
                  ->after('mercado_meta');

            // Certificaciones de PRODUCTO
            $table->boolean('tiene_certificaciones_producto')->nullable()->after('tipo_oferta');
            $table->json('certificaciones_cumple')->nullable()->after('tiene_certificaciones_producto');
            $table->text('certificaciones_cumple_otros')->nullable()->after('certificaciones_cumple');
            $table->json('apoyo_iyem')->nullable()->after('certificaciones_cumple_otros');
            $table->text('apoyo_iyem_otros')->nullable()->after('apoyo_iyem');

            // Certificaciones de SERVICIO
            $table->boolean('tiene_certificaciones_servicio')->nullable()->after('apoyo_iyem_otros');
            $table->text('certificaciones_servicio_detalle')->nullable()->after('tiene_certificaciones_servicio');
        });
    }

    public function down(): void
    {
        Schema::table('restauranteros', function (Blueprint $table) {
            $table->dropColumn([
                'tipo_oferta',
                'tiene_certificaciones_producto',
                'certificaciones_cumple',
                'certificaciones_cumple_otros',
                'apoyo_iyem',
                'apoyo_iyem_otros',
                'tiene_certificaciones_servicio',
                'certificaciones_servicio_detalle',
            ]);
        });
    }
};
