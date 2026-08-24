<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('ine_path')->nullable()->after('profile_photo_path');
            $table->string('csf_path')->nullable()->after('ine_path');
            $table->date('csf_fecha')->nullable()->after('csf_path');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['ine_path', 'csf_path', 'csf_fecha']);
        });
    }
};
