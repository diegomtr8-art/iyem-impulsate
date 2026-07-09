<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('restauranteros', function (Blueprint $table) {
            $table->boolean('acepta_credito')->default(false)->after('solicitado_aprobacion_at');
            $table->decimal('credito_monto_maximo', 10, 2)->nullable()->after('acepta_credito');
            $table->unsignedInteger('credito_tiempo_cantidad')->nullable()->after('credito_monto_maximo');
            $table->string('credito_tiempo_unidad', 20)->nullable()->after('credito_tiempo_cantidad');
            $table->boolean('pago_contraentrega')->default(false)->after('credito_tiempo_unidad');
            $table->boolean('factura')->default(false)->after('pago_contraentrega');
            $table->string('regimen_fiscal', 100)->nullable()->after('factura');
            $table->boolean('perfil_completo')->default(false)->after('regimen_fiscal');
        });
    }

    public function down(): void
    {
        Schema::table('restauranteros', function (Blueprint $table) {
            $table->dropColumn([
                'acepta_credito', 'credito_monto_maximo', 'credito_tiempo_cantidad',
                'credito_tiempo_unidad', 'pago_contraentrega', 'factura',
                'regimen_fiscal', 'perfil_completo',
            ]);
        });
    }
};
