<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Email verification was switched on after people had already signed up
     * without ever being sent a link, so existing accounts are grandfathered
     * in rather than locked out of their workspaces until the mailer works.
     */
    public function up(): void
    {
        DB::table('users')
            ->whereNull('email_verified_at')
            ->update(['email_verified_at' => now()]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Which accounts were verified by this backfill is not recorded, so it cannot be undone.
    }
};
