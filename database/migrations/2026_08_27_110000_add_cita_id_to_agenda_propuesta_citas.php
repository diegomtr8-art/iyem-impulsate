<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Guarda que cita real salio de cada renglon de la propuesta.
     * Sin esto, borrar una agenda borraba TODAS las citas del comprador en
     * el evento, incluidas las que el mismo habia agendado por su cuenta.
     */
    public function up(): void
    {
        Schema::table('agenda_propuesta_citas', function (Blueprint $table) {
            $table->foreignId('cita_id')->nullable()->after('slot_fin')
                  ->constrained('citas')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('agenda_propuesta_citas', function (Blueprint $table) {
            $table->dropConstrainedForeignKey('cita_id');
        });
    }
};
