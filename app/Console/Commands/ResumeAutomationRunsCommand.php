<?php

namespace App\Console\Commands;

use App\Enums\AutomationRunStatus;
use App\Enums\AutomationStatus;
use App\Enums\EmailDeliveryStatus;
use App\Jobs\ProcessAutomationRun;
use App\Jobs\SendAutomationEmailDelivery;
use App\Models\AutomationEmailDelivery;
use App\Models\AutomationRun;
use App\Models\AutomationRunStep;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;

#[Signature('automations:resume')]
#[Description('Re-dispatch pending automation branches, branches whose delay is due, and automation emails whose send job was lost')]
class ResumeAutomationRunsCommand extends Command
{
    /**
     * Redis is not durable storage, so a flush, eviction, or queue reset can
     * strand immediate and delayed branches. Their step rows are the durable
     * work queue this command sweeps back onto Redis. A send step's Queued
     * delivery row plays the same part for its SendAutomationEmailDelivery job.
     *
     * Re-dispatching is safe because AdvanceAutomationRun claims each step, and
     * the send job each delivery, with a conditional update before doing the
     * work. Steps of a paused automation are left parked until it is activated
     * again.
     */
    public function handle(): int
    {
        $resumed = 0;

        $staleBefore = now()->subMinutes(max(5, (int) config('delivery.recovery.stalled_after_minutes', 15)));

        // A send step stays running while its email waits for, or is inside,
        // its send job. That is not a dead worker.
        $heldBySendJob = fn ($deliveries) => $deliveries->where(fn ($held) => $held
            ->where(fn ($query) => $query->where('status', EmailDeliveryStatus::Queued)->whereNull('send_attempted_at'))
            ->orWhere(fn ($query) => $query->where('status', EmailDeliveryStatus::Sending)->where('send_attempted_at', '>', $staleBefore)));

        AutomationRunStep::query()
            ->where('status', 'running')
            ->where('updated_at', '<=', $staleBefore)
            ->whereHas('run', fn ($query) => $query->whereIn('status', AutomationRunStatus::open()))
            ->whereDoesntHave('emailDeliveries', $heldBySendJob)
            ->chunkById(100, function ($steps) use ($staleBefore, $heldBySendJob): void {
                foreach ($steps as $step) {
                    DB::transaction(function () use ($step, $staleBefore, $heldBySendJob): void {
                        $run = AutomationRun::query()->lockForUpdate()->find($step->automation_run_id);
                        $staleStep = AutomationRunStep::query()->whereKey($step->id)
                            ->where('status', 'running')->where('updated_at', '<=', $staleBefore)
                            ->whereDoesntHave('emailDeliveries', $heldBySendJob)
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

        // An email whose send job was lost sits Queued under a running step.
        // Touching the row after the push keeps the next sweep off it for a
        // window, and a failed push leaves it for the next sweep.
        $lostBefore = $staleBefore;
        AutomationEmailDelivery::query()
            ->where('status', EmailDeliveryStatus::Queued)
            ->whereNull('send_attempted_at')
            ->where('updated_at', '<=', $lostBefore)
            ->whereHas('step', fn ($steps) => $steps->where('status', 'running'))
            ->orderBy('id')
            ->chunkById(100, function ($deliveries) use (&$resumed): void {
                foreach ($deliveries as $delivery) {
                    SendAutomationEmailDelivery::dispatch($delivery->id);
                    $delivery->touch();
                    $resumed++;
                }
            });

        // A paused automation holds its branches until it is activated again,
        // which re-dispatches them itself. Branches of a deleted automation
        // still go out so the engine can cancel them.
        AutomationRunStep::query()
            ->dueForDispatch()
            ->whereDoesntHave('run.automation', fn ($query) => $query->where('status', '!=', AutomationStatus::Active))
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
