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
        Schema::create('page_visits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurantero_id')->nullable()->constrained()->nullOnDelete();
            $table->string('url', 500);
            $table->string('device_type', 50)->nullable();
            $table->string('ip', 50)->nullable();
            $table->timestamps();

            $table->index('restaurantero_id');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('page_visits');
    }
};
