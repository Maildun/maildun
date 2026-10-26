<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Conversion events are an audit record. References to subscribers,
     * series, campaigns, and deliveries are nulled rather than cascaded so the
     * history survives their deletion, the same way email_deliveries keep the
     * recipient address after a subscriber is removed. Deleting the workspace
     * removes its events.
     */
    public function up(): void
    {
        Schema::create('email_conversion_events', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->string('external_id');
            $table->char('request_hash', 64);
            $table->string('event_key', 64);
            $table->string('email');
            $table->foreignId('subscriber_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('requested_campaign_series_id')->nullable()->constrained('campaign_series')->nullOnDelete();
            $table->string('attribution_type', 16);
            $table->foreignId('campaign_series_id')->nullable()->constrained('campaign_series')->nullOnDelete();
            $table->foreignId('email_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('email_delivery_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('touched_at')->nullable();
            $table->unsignedBigInteger('value_minor')->nullable();
            $table->char('currency', 3)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->unique(['team_id', 'external_id']);
            $table->index(['team_id', 'email']);
            $table->index(['campaign_series_id', 'attribution_type']);
            $table->index(['email_id', 'attribution_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('email_conversion_events');
    }
};
