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
        Schema::create('team_sender_domains', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->string('domain');
            $table->string('verification_token', 64);
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('verification_checked_at')->nullable();
            $table->foreignId('verified_email_integration_id')
                ->nullable()
                ->constrained('team_email_integrations')
                ->nullOnDelete();
            $table->unsignedInteger('verified_email_integration_version')->nullable();
            $table->timestamps();

            $table->unique(['team_id', 'domain']);
        });

        Schema::table('team_senders', function (Blueprint $table) {
            $table->foreignId('verified_sender_domain_id')
                ->nullable()
                ->after('verified_email_integration_version')
                ->constrained('team_sender_domains')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('team_senders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('verified_sender_domain_id');
        });

        Schema::dropIfExists('team_sender_domains');
    }
};
