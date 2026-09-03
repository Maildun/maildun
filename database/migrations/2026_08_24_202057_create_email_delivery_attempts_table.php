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
        Schema::create('email_delivery_attempts', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('email_delivery_id')->constrained()->cascadeOnDelete();
            $table->foreignId('team_email_integration_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();
            $table->uuid('integration_uuid')->nullable();
            $table->string('integration_name')->nullable();
            $table->string('provider');
            $table->string('status')->default('sending');
            $table->string('provider_message_id')->nullable();
            $table->text('failure_reason')->nullable();
            $table->string('ses_configuration_set')->nullable();
            $table->char('ses_sns_topic_arn_hash', 64)->nullable();
            $table->timestamp('send_attempted_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('delayed_at')->nullable();
            $table->timestamp('bounced_at')->nullable();
            $table->timestamp('complained_at')->nullable();
            $table->timestamps();

            $table->index(['email_delivery_id', 'status']);
            $table->index(['provider', 'ses_sns_topic_arn_hash'], 'email_attempt_provider_topic_index');
            $table->index(
                ['provider', 'provider_message_id', 'ses_sns_topic_arn_hash'],
                'email_attempt_provider_message_topic_index',
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('email_delivery_attempts');
    }
};
