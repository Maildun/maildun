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
     * SES feedback is stored the moment its signature checks out and processed
     * by a queued job afterwards, so an event can now exist before it has been
     * processed. The pending index serves the sweep that re-dispatches events
     * whose job was lost; on Postgres it is built concurrently so feedback keeps
     * arriving while a large table is indexed.
     */
    public function up(): void
    {
        Schema::table('email_provider_events', function (Blueprint $table): void {
            $table->timestamp('processed_at')->nullable()->change();
            $table->timestamp('last_dispatched_at')->nullable()->after('processed_at');
            $table->unsignedSmallInteger('processing_attempts')->default(0)->after('last_dispatched_at');
            $table->text('last_error')->nullable()->after('processing_attempts');
        });

        $concurrently = DB::getDriverName() === 'pgsql' ? 'concurrently ' : '';

        DB::statement("create index {$concurrently}email_provider_events_pending_index on email_provider_events (last_dispatched_at, id) where processed_at is null");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::table('email_provider_events')->whereNull('processed_at')->exists()) {
            throw new RuntimeException('Unprocessed SES feedback prevents a safe rollback. Let the queue process it first.');
        }

        $concurrently = DB::getDriverName() === 'pgsql' ? 'concurrently ' : '';

        DB::statement("drop index {$concurrently}if exists email_provider_events_pending_index");

        Schema::table('email_provider_events', function (Blueprint $table): void {
            $table->dropColumn(['last_dispatched_at', 'processing_attempts', 'last_error']);
            $table->timestamp('processed_at')->nullable(false)->change();
        });
    }
};
