<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('telefono', 20)->nullable()->after('name');
            $table->string('curp', 18)->nullable()->after('telefono');
            $table->enum('active_role', ['comprador', 'proveedor'])->default('comprador')->after('curp');
        });

        // Usuarios que ya tienen rol restaurantero pero NO cliente → active_role = 'proveedor'
        \DB::table('users')
            ->whereIn('id', function ($q) {
                $q->select('model_id')
                    ->from('model_has_roles')
                    ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
                    ->where('roles.name', 'restaurantero')
                    ->where('model_has_roles.model_type', 'App\\Models\\User');
            })
            ->whereNotIn('id', function ($q) {
                $q->select('model_id')
                    ->from('model_has_roles')
                    ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
                    ->where('roles.name', 'cliente')
                    ->where('model_has_roles.model_type', 'App\\Models\\User');
            })
            ->update(['active_role' => 'proveedor']);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['telefono', 'curp', 'active_role']);
        });
    }
};
