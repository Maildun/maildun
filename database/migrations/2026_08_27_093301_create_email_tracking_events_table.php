<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('email_tracking_events', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('email_delivery_id')->constrained()->cascadeOnDelete();
            $table->foreignId('email_link_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('type', 16);
            $table->timestamp('occurred_at');
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('last_dispatched_at')->nullable();
            $table->unsignedSmallInteger('processing_attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->text('user_agent')->nullable();
            $table->char('ip_hash', 64)->nullable();
            $table->boolean('is_bot')->nullable();
            $table->boolean('is_proxy')->nullable();
            $table->timestamps();

            $table->index(
                ['email_delivery_id', 'type', 'occurred_at'],
                'email_tracking_events_delivery_timeline_index',
            );
            $table->index('occurred_at', 'email_tracking_events_retention_index');
        });

        DB::statement(<<<'SQL'
            CREATE INDEX email_tracking_events_link_timeline_index
            ON email_tracking_events (email_link_id, occurred_at)
            WHERE email_link_id IS NOT NULL
            SQL);

        DB::statement(<<<'SQL'
            CREATE INDEX email_tracking_events_pending_index
            ON email_tracking_events (last_dispatched_at, id)
            WHERE processed_at IS NULL
            SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('email_tracking_events');
    }
};
