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
        Schema::create('email_address_healths', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->string('email');
            $table->string('status')->default('unknown');
            $table->string('reason')->nullable();
            $table->string('provider')->nullable();
            $table->text('detail')->nullable();
            $table->unsignedInteger('hard_bounce_count')->default(0);
            $table->unsignedInteger('soft_bounce_count')->default(0);
            $table->unsignedInteger('complaint_count')->default(0);
            $table->unsignedInteger('failure_count')->default(0);
            $table->timestamp('last_event_at')->nullable();
            $table->timestamp('last_delivered_at')->nullable();
            $table->timestamp('last_failed_at')->nullable();
            $table->timestamp('suppressed_at')->nullable();
            $table->timestamps();

            $table->unique(['team_id', 'email']);
            $table->index(
                ['team_id', 'status', 'last_event_at'],
                'email_health_team_status_event_index',
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('email_address_healths');
    }
};
