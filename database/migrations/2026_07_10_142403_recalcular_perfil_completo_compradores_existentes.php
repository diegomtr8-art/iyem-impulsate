<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * El wizard de comprador (CompletarPerfil.vue) ahora exige 'necesidades' y
     * expone 'es_restaurantero'. Muchas cuentas ya existentes quedaron con
     * perfil_completo=true desde antes (datos de seed o el wizard viejo, más
     * simple) sin tener realmente los campos requeridos actuales. Sin este
     * backfill, EnsureProfileComplete nunca les mostraría el modal de nuevo.
     */
    public function up(): void
    {
        $clienteRoleId = DB::table('roles')->where('name', 'cliente')->value('id');

        if (!$clienteRoleId) {
            return;
        }

        DB::table('users')
            ->join('model_has_roles', function ($join) use ($clienteRoleId) {
                $join->on('model_has_roles.model_id', '=', 'users.id')
                     ->where('model_has_roles.model_type', '=', 'App\\Models\\User')
                     ->where('model_has_roles.role_id', '=', $clienteRoleId);
            })
            ->where(function ($q) {
                $q->whereNull('users.telefono')->orWhere('users.telefono', '')
                  ->orWhereNull('users.nombre_empresa')->orWhere('users.nombre_empresa', '')
                  ->orWhereNull('users.municipio')->orWhere('users.municipio', '')
                  ->orWhereNull('users.camara_asociacion')->orWhere('users.camara_asociacion', '')
                  ->orWhereNull('users.necesidades')->orWhere('users.necesidades', '')
                  ->orWhere(function ($q2) {
                      $q2->where('users.camara_asociacion', 'CANIRAC')
                         ->where(function ($q3) {
                             $q3->whereNull('users.nombre_establecimiento')
                                ->orWhere('users.nombre_establecimiento', '');
                         });
                  });
            })
            ->update(['users.perfil_completo' => false]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Intencionalmente vacío: no sabemos cuáles perfiles fueron marcados
        // incompletos por este backfill vs. los que ya estaban así antes.
    }
};
