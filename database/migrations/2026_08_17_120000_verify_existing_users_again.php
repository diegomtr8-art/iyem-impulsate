<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Second grandfathering pass: users registered since the 2026-06-03 backfill
        // are still stuck unverified, hitting the SMTP-dependent 'verified' middleware
        // and 500ing when Gmail rejects the app credentials. See verify_existing_users.
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
