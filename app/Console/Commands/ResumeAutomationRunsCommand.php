<?php

namespace App\Console\Commands;

use App\Enums\AutomationRunStatus;
use App\Jobs\ProcessAutomationRun;
use App\Models\AutomationRunStep;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('automations:resume')]
#[Description('Re-dispatch pending automation branches and branches whose delay is due')]
class ResumeAutomationRunsCommand extends Command
{
    /**
     * Redis is not durable storage, so a flush, eviction, or queue reset can
     * strand immediate and delayed branches. Their step rows are the durable
     * work queue this command sweeps back onto Redis.
     *
     * Re-dispatching is safe because AdvanceAutomationRun claims each step with
     * a conditional update before executing it.
     */
    public function handle(): int
    {
        $resumed = 0;

        AutomationRunStep::query()
            ->whereHas('run', fn ($query) => $query->whereIn('status', AutomationRunStatus::open()))
            ->where(function ($query): void {
                $query->where('status', 'pending')
                    ->orWhere(function ($query): void {
                        $query->where('status', 'waiting')
                            ->whereNotNull('scheduled_at')
                            ->where('scheduled_at', '<=', now());
                    });
            })
            ->orderBy('id')
            ->chunkById(100, function ($steps) use (&$resumed): void {
                foreach ($steps as $step) {
                    ProcessAutomationRun::dispatch($step->automation_run_id, $step->node_id);
                    $resumed++;
                }
            });

        if ($resumed > 0) {
            $this->info(__(':count automation branch(es) resumed.', ['count' => $resumed]));
        }

        return self::SUCCESS;
    }
}
