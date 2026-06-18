<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('camara_asociacion', 50)->nullable()->after('acepta_aviso_at');
            $table->string('nombre_establecimiento', 255)->nullable()->after('camara_asociacion');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['camara_asociacion', 'nombre_establecimiento']);
        });
    }
};
