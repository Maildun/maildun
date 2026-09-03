<?php

use App\Actions\Automations\AdvanceAutomationRun;
use App\Enums\AutomationAction;
use App\Enums\AutomationRunStatus;
use App\Jobs\ProcessAutomationRun;
use App\Models\Audience;
use App\Models\Automation;
use App\Models\AutomationRun;
use App\Models\Subscriber;
use Illuminate\Support\Facades\Queue;

beforeEach(fn () => Queue::fake());

function parkedRun(AutomationRunStatus $status, ?string $scheduledAt): AutomationRun
{
    $automation = Automation::factory()->active()->create();
    $subscriber = Subscriber::factory()->for(Audience::factory()->for($automation->team))->create();

    $run = $automation->runs()->create([
        'subscriber_id' => $subscriber->id,
        'status' => $status,
        'graph' => [
            'nodes' => [
                ['id' => 'trigger', 'type' => 'trigger', 'data' => ['kind' => 'subscribed']],
                ['id' => 'wait', 'type' => 'delay', 'data' => ['amount' => 2, 'unit' => 'hours']],
                ['id' => 'after', 'type' => 'action', 'data' => ['kind' => AutomationAction::AddTag->value, 'tag_uuid' => 'x']],
            ],
            'edges' => [
                ['source' => 'trigger', 'target' => 'wait'],
                ['source' => 'wait', 'target' => 'after'],
            ],
        ],
        'current_node_id' => 'wait',
        'scheduled_at' => $scheduledAt,
    ]);

    $run->steps()->create([
        'node_id' => 'wait',
        'status' => match ($status) {
            AutomationRunStatus::Pending => 'pending',
            AutomationRunStatus::Running => 'running',
            AutomationRunStatus::Waiting => 'waiting',
            AutomationRunStatus::Completed => 'completed',
            AutomationRunStatus::Failed => 'failed',
            AutomationRunStatus::Cancelled => 'cancelled',
        },
        'scheduled_at' => $scheduledAt,
    ]);

    return $run;
}

test('a run whose delay is due is put back on the queue', function () {
    $run = parkedRun(AutomationRunStatus::Waiting, now()->subHour()->toDateTimeString());

    $this->artisan('automations:resume')->assertSuccessful();

    Queue::assertPushed(
        ProcessAutomationRun::class,
        fn (ProcessAutomationRun $job): bool => $job->automationRunId === $run->id
            && $job->nodeId === 'wait',
    );
});

test('a run whose delay has not arrived is left parked', function () {
    parkedRun(AutomationRunStatus::Waiting, now()->addHour()->toDateTimeString());

    $this->artisan('automations:resume')->assertSuccessful();

    Queue::assertNothingPushed();
});

test('pending branches are put back on the queue', function () {
    $run = parkedRun(AutomationRunStatus::Pending, null);

    $this->artisan('automations:resume')->assertSuccessful();

    Queue::assertPushed(
        ProcessAutomationRun::class,
        fn (ProcessAutomationRun $job): bool => $job->automationRunId === $run->id
            && $job->nodeId === 'wait',
    );
});

test('branches that are not ready to resume are left alone', function () {
    // Every one of these is either already queued, finished, or deliberately
    // stopped — none of them is a lost delayed job.
    foreach ([
        AutomationRunStatus::Running,
        AutomationRunStatus::Completed,
        AutomationRunStatus::Failed,
        AutomationRunStatus::Cancelled,
    ] as $status) {
        parkedRun($status, now()->subHour()->toDateTimeString());
    }

    // A waiting run with no scheduled time was never parked on a delay.
    parkedRun(AutomationRunStatus::Waiting, null);

    $this->artisan('automations:resume')->assertSuccessful();

    Queue::assertNothingPushed();
});

test('a resumed run walks on from the step it was parked at', function () {
    $run = parkedRun(AutomationRunStatus::Waiting, now()->subHour()->toDateTimeString());

    $this->artisan('automations:resume')->assertSuccessful();

    // The sweeper only re-queues; the engine still does the work.
    app(AdvanceAutomationRun::class)->handle($run);

    expect($run->refresh()->current_node_id)->toBe('after')
        ->and($run->steps()->where('node_id', 'wait')->value('status'))->toBe('completed');
});

test('a duplicate sweep cannot double-advance the same run', function () {
    $run = parkedRun(AutomationRunStatus::Waiting, now()->subHour()->toDateTimeString());

    $this->artisan('automations:resume')->assertSuccessful();
    $this->artisan('automations:resume')->assertSuccessful();

    Queue::assertPushed(ProcessAutomationRun::class, 2);

    // Both jobs hold the same pre-claim snapshot; only the first claim wins.
    $first = AutomationRun::query()->findOrFail($run->id);
    $second = AutomationRun::query()->findOrFail($run->id);

    app(AdvanceAutomationRun::class)->handle($first);
    app(AdvanceAutomationRun::class)->handle($second);

    expect($run->refresh()->steps()->where('node_id', 'wait')->count())->toBe(1)
        ->and($run->current_node_id)->toBe('after');
});
