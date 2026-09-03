<?php

namespace App\Jobs;

use App\Actions\Automations\AdvanceAutomationRun;
use App\Enums\AutomationRunStatus;
use App\Models\AutomationRun;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class ProcessAutomationRun implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 60;

    /** @var list<int> */
    public array $backoff = [30, 120, 300];

    public function __construct(
        public int $automationRunId,
        public ?string $nodeId = null,
    ) {}

    public function handle(AdvanceAutomationRun $advance): void
    {
        $run = AutomationRun::query()->find($this->automationRunId);

        if ($run === null) {
            return;
        }

        $advance->handle($run, $this->nodeId);
    }

    public function failed(?Throwable $exception): void
    {
        $run = AutomationRun::query()
            ->whereKey($this->automationRunId)
            ->whereIn('status', AutomationRunStatus::open())
            ->first();

        if ($run === null) {
            return;
        }

        $reason = $exception?->getMessage() ?? __('Automation run failed.');

        $nodeId = $this->nodeId ?? $run->current_node_id;

        if (is_string($nodeId) && $nodeId !== '') {
            $step = $run->steps()->updateOrCreate(
                ['node_id' => $nodeId],
                [
                    'status' => 'failed',
                    'result' => ['error' => $reason],
                    'scheduled_at' => null,
                    'processed_at' => now(),
                ],
            );

            $run->steps()
                ->whereKeyNot($step->id)
                ->whereIn('status', ['pending', 'running', 'waiting'])
                ->update([
                    'status' => 'cancelled',
                    'scheduled_at' => null,
                    'processed_at' => now(),
                ]);
        }

        $run->update([
            'status' => AutomationRunStatus::Failed,
            'current_node_id' => $nodeId,
            'failure_reason' => $reason,
            'failed_at' => now(),
            'scheduled_at' => null,
        ]);
    }
}
