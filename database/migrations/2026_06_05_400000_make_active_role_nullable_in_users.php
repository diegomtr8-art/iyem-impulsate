<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // active_role puede ser null mientras el usuario no ha seleccionado su rol
        Schema::table('users', function (Blueprint $table) {
            $table->enum('active_role', ['comprador', 'proveedor'])->nullable()->default(null)->change();
        });
    }

    public function down(): void
    {
        // Rellenar nulls antes de volver a NOT NULL
        \DB::table('users')->whereNull('active_role')->update(['active_role' => 'comprador']);

        Schema::table('users', function (Blueprint $table) {
            $table->enum('active_role', ['comprador', 'proveedor'])->default('comprador')->change();
        });
    }
};
