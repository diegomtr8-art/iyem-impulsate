<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * El token de la pantalla TV era la constante 'impulsate-tv-2026',
     * publicada en DEPLOY.md y en los scripts de deploy del repo. Cualquiera
     * podia leer la agenda del dia completa desde internet.
     */
    public function up(): void
    {
        Schema::table('eventos', function (Blueprint $table) {
            $table->string('tv_token', 64)->nullable()->unique()->after('activa');
        });

        foreach (DB::table('eventos')->pluck('id') as $id) {
            DB::table('eventos')->where('id', $id)->update(['tv_token' => Str::random(48)]);
        }
    }

    public function down(): void
    {
        Schema::table('eventos', function (Blueprint $table) {
            $table->dropColumn('tv_token');
        });
    }
};
