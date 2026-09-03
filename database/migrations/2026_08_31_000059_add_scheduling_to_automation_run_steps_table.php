<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('automation_run_steps', function (Blueprint $table) {
            $table->timestamp('scheduled_at')->nullable();
            $table->index(['status', 'scheduled_at']);
        });

        DB::table('automation_runs')
            ->whereIn('status', ['pending', 'running', 'waiting'])
            ->whereNotNull('current_node_id')
            ->orderBy('id')
            ->chunkById(100, function ($runs): void {
                foreach ($runs as $run) {
                    $step = DB::table('automation_run_steps')
                        ->where('automation_run_id', $run->id)
                        ->where('node_id', $run->current_node_id)
                        ->first();

                    $status = match (true) {
                        $step?->status === 'sending' => 'running',
                        $run->status === 'waiting' => 'waiting',
                        default => 'pending',
                    };
                    $scheduledAt = $run->status === 'waiting' ? $run->scheduled_at : null;

                    if ($step !== null) {
                        DB::table('automation_run_steps')
                            ->where('id', $step->id)
                            ->update([
                                'status' => $status,
                                'scheduled_at' => $scheduledAt,
                                'updated_at' => now(),
                            ]);

                        continue;
                    }

                    DB::table('automation_run_steps')->insert([
                        'uuid' => (string) Str::uuid(),
                        'automation_run_id' => $run->id,
                        'node_id' => $run->current_node_id,
                        'status' => $status,
                        'scheduled_at' => $scheduledAt,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('automation_run_steps', function (Blueprint $table) {
            $table->dropIndex(['status', 'scheduled_at']);
            $table->dropColumn('scheduled_at');
        });
    }
};
