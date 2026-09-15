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
        Schema::create('automation_email_deliveries', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('automation_run_id')->constrained()->cascadeOnDelete();
            $table->foreignId('transactional_email_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('subscriber_id')->nullable()->constrained()->nullOnDelete();
            $table->string('to_address');
            $table->string('status')->default('sending');
            $table->string('provider');
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

            $table->index(['automation_run_id', 'status']);
            $table->index(
                ['provider', 'ses_sns_topic_arn_hash'],
                'automation_delivery_provider_topic_index',
            );
            $table->index(
                ['provider', 'provider_message_id', 'ses_sns_topic_arn_hash'],
                'automation_delivery_message_topic_index',
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('automation_email_deliveries');
    }
};
