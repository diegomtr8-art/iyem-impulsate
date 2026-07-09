<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('restauranteros', function (Blueprint $table) {
            $table->boolean('entrega_domicilio')->nullable()->after('regimen_fiscal');
            $table->string('cobertura_entrega', 30)->nullable()->after('entrega_domicilio');
            $table->string('forma_entrega', 30)->nullable()->after('cobertura_entrega');
        });
    }

    public function down(): void
    {
        Schema::table('restauranteros', function (Blueprint $table) {
            $table->dropColumn(['entrega_domicilio', 'cobertura_entrega', 'forma_entrega']);
        });
    }
};
