<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Clean up any orphaned edicion_id values before adding FK constraints.
        // This is a safety net for databases that may have inconsistent data.
        $edicionIds = DB::table('ediciones')->pluck('id');

        DB::table('citas')
            ->whereNotNull('edicion_id')
            ->whereNotIn('edicion_id', $edicionIds)
            ->update(['edicion_id' => null]);

        DB::table('restauranteros')
            ->whereNotNull('edicion_id')
            ->whereNotIn('edicion_id', $edicionIds)
            ->update(['edicion_id' => null]);

        Schema::table('citas', function (Blueprint $table) {
            $table->foreign('edicion_id')
                  ->references('id')
                  ->on('ediciones')
                  ->onDelete('set null');
        });

        Schema::table('restauranteros', function (Blueprint $table) {
            $table->foreign('edicion_id')
                  ->references('id')
                  ->on('ediciones')
                  ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('citas', function (Blueprint $table) {
            $table->dropForeign(['edicion_id']);
        });

        Schema::table('restauranteros', function (Blueprint $table) {
            $table->dropForeign(['edicion_id']);
        });
    }
};
