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
        Schema::table('email_deliveries', function (Blueprint $table) {
            $table->unique(['email_id', 'subscriber_id'], 'email_deliveries_email_subscriber_unique');
            $table->index(
                ['status', 'send_attempted_at', 'updated_at'],
                'email_deliveries_recovery_index',
            );
        });

        Schema::table('transactional_email_deliveries', function (Blueprint $table) {
            $table->index(
                ['status', 'send_attempted_at', 'updated_at'],
                'transactional_deliveries_recovery_index',
            );
        });

        Schema::table('emails', function (Blueprint $table) {
            $table->index(['status', 'send_started_at'], 'emails_recovery_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('email_deliveries', function (Blueprint $table) {
            $table->dropUnique('email_deliveries_email_subscriber_unique');
            $table->dropIndex('email_deliveries_recovery_index');
        });

        Schema::table('transactional_email_deliveries', function (Blueprint $table) {
            $table->dropIndex('transactional_deliveries_recovery_index');
        });

        Schema::table('emails', function (Blueprint $table) {
            $table->dropIndex('emails_recovery_index');
        });
    }
};
