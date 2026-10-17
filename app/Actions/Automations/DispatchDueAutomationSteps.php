<?php

namespace App\Actions\Automations;

use App\Enums\AutomationRunStatus;
use App\Enums\AutomationStatus;
use App\Jobs\ProcessAutomationRun;
use App\Models\Automation;
use App\Models\AutomationRunStep;

class DispatchDueAutomationSteps
{
    /**
     * Put every open step that is ready to move back on the queue: pending
     * steps, and waits whose time has passed. Pass an automation to limit the
     * sweep to its runs.
     *
     * Steps of a paused automation stay parked. AdvanceAutomationRun would only
     * skip them, so sweeping them would re-queue the same no-op jobs every run;
     * activating the automation calls this again for its own runs instead.
     *
     * Re-dispatching is safe because AdvanceAutomationRun claims each step with
     * a conditional update before executing it.
     */
    public function handle(?Automation $automation = null): int
    {
        $dispatched = 0;

        AutomationRunStep::query()
            ->whereHas('run', function ($query) use ($automation): void {
                $query->whereIn('status', AutomationRunStatus::open())
                    ->when($automation, fn ($query, Automation $automation) => $query->where('automation_id', $automation->id))
                    ->whereDoesntHave('automation', fn ($query) => $query->where('status', AutomationStatus::Paused));
            })
            ->where(function ($query): void {
                $query->where('status', 'pending')
                    ->orWhere(function ($query): void {
                        $query->where('status', 'waiting')
                            ->whereNotNull('scheduled_at')
                            ->where('scheduled_at', '<=', now());
                    });
            })
            ->chunkById(100, function ($steps) use (&$dispatched): void {
                foreach ($steps as $step) {
                    ProcessAutomationRun::dispatch($step->automation_run_id, $step->node_id);
                    $dispatched++;
                }
            });

        return $dispatched;
    }
}
