<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('citas', function (Blueprint $table) {
            $table->unsignedBigInteger('edicion_id')->nullable()->after('id');
        });

        // Asignar todas las citas existentes a la edición inicial activa
        $edicionId = DB::table('ediciones')->where('activa', true)->value('id');
        if ($edicionId) {
            DB::table('citas')->whereNull('edicion_id')->update(['edicion_id' => $edicionId]);
        }
    }

    public function down(): void
    {
        Schema::table('citas', function (Blueprint $table) {
            $table->dropColumn('edicion_id');
        });
    }
};
