<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('rol_seleccionado')->default(false)->after('active_role');
        });

        // Los usuarios que ya tienen active_role configurado no deben ver el selector
        DB::table('users')
            ->whereNotNull('active_role')
            ->update(['rol_seleccionado' => true]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('rol_seleccionado');
        });
    }
};
