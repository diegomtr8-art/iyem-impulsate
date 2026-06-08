<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('rfc', 13)->nullable()->after('curp');
            $table->string('municipio', 100)->nullable()->after('rfc');
            $table->string('nombre_empresa', 200)->nullable()->after('municipio');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['rfc', 'municipio', 'nombre_empresa']);
        });
    }
};
