<?php

namespace App\Console\Commands;

use App\Actions\Automations\AdvanceAutomationRun;
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
#[Description('Re-dispatch automation branches and emails whose job was lost, and close runs and emails nothing will finish')]
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
     * work. Only work that has not moved for the stall window goes back on the
     * queue, and it is touched when it does, so a branch still waiting in a
     * busy queue is not pushed again every five minutes. Steps of a paused
     * automation, and of a deleted workspace, are left parked.
     */
    public function handle(AdvanceAutomationRun $advance): int
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
        // window, and a failed push leaves it for the next sweep. A throttled
        // job can keep waiting for a slot until its retry window closes, so
        // only a row older than that window has no job left to run it.
        $lostBefore = (int) config('delivery.rate_limit.per_second') > 0
            ? $staleBefore->copy()->subHours(SendAutomationEmailDelivery::RETRY_WINDOW_HOURS)
            : $staleBefore;
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

        // A queued email whose step is no longer running, or is gone with its
        // deleted subscriber, will never be sent. Its job closes it when it
        // runs; this closes the ones whose job was lost too.
        foreach ([
            __('The subscriber was deleted before this email was sent.') => fn ($deliveries) => $deliveries->whereNull('subscriber_id'),
            __('The automation run ended before this email was sent.') => fn ($deliveries) => $deliveries->whereNotNull('subscriber_id'),
        ] as $reason => $whose) {
            AutomationEmailDelivery::query()
                ->where('status', EmailDeliveryStatus::Queued)
                ->whereNull('send_attempted_at')
                ->where('updated_at', '<=', $lostBefore)
                ->whereDoesntHave('step', fn ($steps) => $steps->where('status', 'running'))
                ->where($whose)
                ->update([
                    'status' => EmailDeliveryStatus::Cancelled,
                    'failure_reason' => $reason,
                ]);
        }

        // A paused automation holds its branches until it is activated again,
        // which re-dispatches them itself. Branches of a deleted automation
        // still go out so the engine can cancel them; those of a deleted
        // workspace do not, because its automations must stop running.
        AutomationRunStep::query()
            ->dueForDispatch()
            ->stalledSince($staleBefore)
            ->whereDoesntHave('run.automation', fn ($query) => $query->where('status', '!=', AutomationStatus::Active))
            ->whereDoesntHave('run.automation.team', fn ($query) => $query->onlyTrashed())
            ->orderBy('id')
            ->chunkById(100, function ($steps) use (&$resumed): void {
                foreach ($steps as $step) {
                    ProcessAutomationRun::dispatch($step->automation_run_id, $step->node_id);
                    $step->touch();
                    $resumed++;
                }
            });

        // An open run with no open branch has nothing left to close it, and
        // the open-run index keeps its subscriber out of the automation.
        AutomationRun::query()
            ->whereIn('status', AutomationRunStatus::open())
            ->where('updated_at', '<=', $staleBefore)
            ->whereDoesntHave('steps', fn ($steps) => $steps->whereIn('status', ['pending', 'running', 'waiting']))
            ->select('id')
            ->chunkById(100, function ($runs) use ($advance): void {
                foreach ($runs as $run) {
                    $advance->synchronizeRun($run);
                }
            });

        if ($resumed > 0) {
            $this->info(__(':count automation branch(es) resumed.', ['count' => $resumed]));
        }

        return self::SUCCESS;
    }
}
