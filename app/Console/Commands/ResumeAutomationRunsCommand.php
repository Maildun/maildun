<?php

namespace App\Console\Commands;

use App\Actions\Automations\DispatchDueAutomationSteps;
use App\Enums\AutomationRunStatus;
use App\Enums\EmailDeliveryStatus;
use App\Jobs\ProcessAutomationRun;
use App\Models\AutomationEmailDelivery;
use App\Models\AutomationRun;
use App\Models\AutomationRunStep;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;

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
     * a conditional update before executing it. Steps of a paused automation
     * are left parked until it is activated again.
     */
    public function handle(DispatchDueAutomationSteps $dispatchDueSteps): int
    {
        $staleBefore = now()->subMinutes(max(5, (int) config('delivery.recovery.stalled_after_minutes', 15)));
        AutomationRunStep::query()
            ->where('status', 'running')
            ->where('updated_at', '<=', $staleBefore)
            ->whereHas('run', fn ($query) => $query->whereIn('status', AutomationRunStatus::open()))
            ->chunkById(100, function ($steps) use ($staleBefore): void {
                foreach ($steps as $step) {
                    DB::transaction(function () use ($step, $staleBefore): void {
                        $run = AutomationRun::query()->lockForUpdate()->find($step->automation_run_id);
                        $staleStep = AutomationRunStep::query()->whereKey($step->id)
                            ->where('status', 'running')->where('updated_at', '<=', $staleBefore)
                            ->lockForUpdate()->first();

                        if ($run === null || ! $run->status->isOpen() || $staleStep === null) {
                            return;
                        }

                        $reason = __('The automation worker stopped reporting. Email delivery may be unconfirmed and will not be retried automatically.');
                        (new ProcessAutomationRun($run->id, $staleStep->node_id))->failed(new RuntimeException($reason));
                        AutomationEmailDelivery::query()->where('automation_run_id', $run->id)
                            ->where('status', EmailDeliveryStatus::Sending)
                            ->where('updated_at', '<=', $staleBefore)
                            ->update(['status' => EmailDeliveryStatus::Failed, 'failure_reason' => $reason]);
                    });
                }
            });

        $resumed = $dispatchDueSteps->handle();

        if ($resumed > 0) {
            $this->info(__(':count automation branch(es) resumed.', ['count' => $resumed]));
        }

        return self::SUCCESS;
    }
}
