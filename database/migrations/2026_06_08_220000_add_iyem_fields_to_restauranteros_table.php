<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('restauranteros', function (Blueprint $table) {
            $table->string('razon_social', 200)->nullable()->after('nombre_restaurante');
            $table->string('nombre_representante', 200)->nullable()->after('razon_social');
            $table->string('curp_representante', 18)->nullable()->after('nombre_representante');
            $table->date('fecha_inicio_operaciones')->nullable()->after('curp_representante');
            $table->unsignedSmallInteger('num_empleados')->nullable()->after('fecha_inicio_operaciones');
            $table->boolean('domicilio_en_yucatan')->nullable()->after('num_empleados');
            $table->text('mercado_meta')->nullable()->after('domicilio_en_yucatan');
            $table->text('tiempo_vida_anaquel')->nullable()->after('mercado_meta');
            $table->json('requisitos_alimentos')->nullable()->after('tiempo_vida_anaquel');
            $table->json('apoyo_requisitos')->nullable()->after('requisitos_alimentos');
            $table->boolean('requiere_refrigeracion')->nullable()->after('apoyo_requisitos');
            $table->boolean('requiere_congelacion')->nullable()->after('requiere_refrigeracion');
        });
    }

    public function down(): void
    {
        Schema::table('restauranteros', function (Blueprint $table) {
            $table->dropColumn([
                'razon_social', 'nombre_representante', 'curp_representante',
                'fecha_inicio_operaciones', 'num_empleados', 'domicilio_en_yucatan',
                'mercado_meta', 'tiempo_vida_anaquel', 'requisitos_alimentos',
                'apoyo_requisitos', 'requiere_refrigeracion', 'requiere_congelacion',
            ]);
        });
    }
};
