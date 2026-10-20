<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Postgres cannot build an index concurrently inside a transaction.
     */
    public $withinTransaction = false;

    /**
     * Run the migrations.
     *
     * The SES feedback heartbeat reads the newest event of a topic and the
     * newest send of a connection. Postgres does not index foreign keys, so
     * without these both reads scan tables that grow with every message. On
     * Postgres the indexes are built concurrently so sending is not blocked
     * while a large table is indexed.
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('drop index concurrently if exists email_attempts_integration_sent_index');
            DB::statement('create index concurrently email_attempts_integration_sent_index on email_delivery_attempts (team_email_integration_id, sent_at)');
            DB::statement('drop index concurrently if exists email_events_topic_created_index');
            DB::statement('create index concurrently email_events_topic_created_index on email_provider_events (ses_sns_topic_arn_hash, created_at)');

            return;
        }

        Schema::table('email_delivery_attempts', function (Blueprint $table) {
            $table->index(['team_email_integration_id', 'sent_at'], 'email_attempts_integration_sent_index');
        });

        Schema::table('email_provider_events', function (Blueprint $table) {
            $table->index(['ses_sns_topic_arn_hash', 'created_at'], 'email_events_topic_created_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('drop index concurrently if exists email_events_topic_created_index');
            DB::statement('drop index concurrently if exists email_attempts_integration_sent_index');

            return;
        }

        Schema::table('email_provider_events', function (Blueprint $table) {
            $table->dropIndex('email_events_topic_created_index');
        });

        Schema::table('email_delivery_attempts', function (Blueprint $table) {
            $table->dropIndex('email_attempts_integration_sent_index');
        });
    }
};
