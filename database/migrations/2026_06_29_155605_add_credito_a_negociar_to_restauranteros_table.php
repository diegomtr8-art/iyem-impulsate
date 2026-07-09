<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('restauranteros', function (Blueprint $table) {
            $table->boolean('credito_a_negociar')->nullable()->default(false)->after('credito_tiempo_unidad');
        });
    }

    public function down(): void
    {
        Schema::table('restauranteros', function (Blueprint $table) {
            $table->dropColumn('credito_a_negociar');
        });
    }
};
