<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Record how the latest test copy actually went, not only that one was
     * queued: queued, sent or failed, who it went to, and why it failed.
     */
    public function up(): void
    {
        foreach (['emails', 'transactional_emails'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->string('last_test_status')->nullable()->after('last_tested_at');
                $table->string('last_test_recipient')->nullable()->after('last_test_status');
                $table->text('last_test_error')->nullable()->after('last_test_recipient');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (['emails', 'transactional_emails'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->dropColumn(['last_test_status', 'last_test_recipient', 'last_test_error']);
            });
        }
    }
};
