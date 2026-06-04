<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Users who registered before email verification was enforced are trusted.
        // Mark them as verified so they are not locked out after this change.
        DB::table('users')
            ->whereNull('email_verified_at')
            ->update(['email_verified_at' => now()]);
    }

    public function down(): void
    {
        // Intentionally empty: we cannot know which users were actually verified
        // vs which were grandfathered, so we do not reverse this.
    }
};
