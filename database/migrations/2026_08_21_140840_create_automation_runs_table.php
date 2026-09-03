<?php

use App\Enums\AutomationRunStatus;
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
        Schema::create('automation_runs', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('automation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscriber_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('pending')->index();
            $table->json('graph');
            $table->json('context')->nullable();
            $table->string('current_node_id')->nullable();
            $table->timestamp('scheduled_at')->nullable()->index();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->text('failure_reason')->nullable();
            $table->timestamps();

            $table->index(['automation_id', 'subscriber_id', 'status']);
            $table->index(['status', 'scheduled_at']);
        });

        $open = array_column(AutomationRunStatus::open(), 'value');
        $statuses = implode(', ', array_map(fn (string $status): string => "'{$status}'", $open));

        DB::statement(
            "create unique index automation_runs_open_subscriber_unique on automation_runs (automation_id, subscriber_id) where status in ({$statuses})"
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('drop index if exists automation_runs_open_subscriber_unique');

        Schema::dropIfExists('automation_runs');
    }
};
