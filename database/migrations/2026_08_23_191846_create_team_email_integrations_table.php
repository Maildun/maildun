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
        Schema::create('team_email_integrations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->string('name')->nullable();
            $table->string('provider');
            $table->text('settings');
            $table->timestamp('connected_at')->nullable();
            $table->timestamp('last_tested_at')->nullable();
            $table->string('authorized_from_address')->nullable();
            $table->timestamp('sender_authorized_at')->nullable();
            $table->string('ses_sns_topic_arn_hash', 64)->nullable()->index();
            $table->timestamps();

            $table->index(['team_id', 'provider']);
        });

        Schema::table('teams', function (Blueprint $table) {
            $table->foreignId('active_email_integration_id')
                ->nullable()
                ->index()
                ->constrained('team_email_integrations')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->dropIndex(['active_email_integration_id']);
            $table->dropConstrainedForeignId('active_email_integration_id');
        });

        Schema::dropIfExists('team_email_integrations');
    }
};
