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
        Schema::create('transactional_email_deliveries', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('transactional_email_id')->constrained()->cascadeOnDelete();
            $table->foreignId('team_api_key_id')->nullable()->constrained()->nullOnDelete();
            $table->string('idempotency_key')->nullable();
            $table->char('request_hash', 64)->nullable();
            $table->string('to_address');
            $table->string('subject');
            $table->text('html');
            $table->string('from_name');
            $table->string('from_address');
            $table->string('reply_to')->nullable();
            $table->string('status')->default('queued');
            $table->string('provider');
            $table->boolean('uses_team_email_integration')->default(false);
            $table->string('provider_message_id')->nullable()->index();
            $table->text('failure_reason')->nullable();
            $table->timestamp('send_attempted_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->unique(['team_id', 'idempotency_key']);
            $table->index(['transactional_email_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactional_email_deliveries');
    }
};
