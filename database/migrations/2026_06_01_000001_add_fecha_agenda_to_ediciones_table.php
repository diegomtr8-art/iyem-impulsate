<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ediciones', function (Blueprint $table) {
            $table->date('fecha_inicio_agenda')->nullable()->after('fecha_inicio');
            $table->date('fecha_fin_agenda')->nullable()->after('fecha_inicio_agenda');
        });
    }

    public function down(): void
    {
        Schema::table('ediciones', function (Blueprint $table) {
            $table->dropColumn(['fecha_inicio_agenda', 'fecha_fin_agenda']);
        });
    }
};
