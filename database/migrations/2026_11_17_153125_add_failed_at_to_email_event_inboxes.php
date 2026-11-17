<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * A tracking or SES feedback event that keeps failing is given up on
     * after a capped number of attempts instead of being re-queued forever.
     * failed_at marks it as dead-lettered; last_error keeps the reason.
     */
    public function up(): void
    {
        foreach (['email_tracking_events', 'email_provider_events'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->timestamp('failed_at')->nullable()->after('last_error');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (['email_tracking_events', 'email_provider_events'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->dropColumn('failed_at');
            });
        }
    }
};
