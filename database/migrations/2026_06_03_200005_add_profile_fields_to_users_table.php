<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->text('necesidades')->nullable()->after('curp');
            $table->boolean('perfil_completo')->default(false)->after('active_role');
        });

        // Usuarios existentes tienen perfil completo (registrados antes de este flujo)
        \DB::table('users')->update(['perfil_completo' => true]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['necesidades', 'perfil_completo']);
        });
    }
};
