<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('citas', function (Blueprint $table) {
            $table->string('mesa', 20)->nullable()->after('notas');
            $table->enum('estado_tv', ['pendiente','llamando','en_curso','finalizada','ausente','reprogramada'])
                  ->default('pendiente')->after('mesa');
            $table->timestamp('hora_real_inicio')->nullable()->after('estado_tv');
            $table->timestamp('hora_real_fin')->nullable()->after('hora_real_inicio');
            $table->timestamp('llamada_at')->nullable()->after('hora_real_fin');
            $table->index('estado_tv');
        });
    }

    public function down(): void
    {
        Schema::table('citas', function (Blueprint $table) {
            $table->dropIndex(['estado_tv']);
            $table->dropColumn(['mesa','estado_tv','hora_real_inicio','hora_real_fin','llamada_at']);
        });
    }
};
