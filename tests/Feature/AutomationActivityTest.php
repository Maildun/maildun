<?php

use App\Enums\AutomationAction;
use App\Enums\AutomationCondition;
use App\Enums\AutomationRunStatus;
use App\Enums\AutomationTrigger;
use App\Models\Audience;
use App\Models\Automation;
use App\Models\AutomationRun;
use App\Models\Subscriber;
use App\Models\Tag;
use App\Models\Team;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * A run whose graph carries a trigger, a tag action, and a condition, so the
 * step trail has something to label.
 */
function activityRun(
    Automation $automation,
    Subscriber $subscriber,
    AutomationRunStatus $status = AutomationRunStatus::Completed,
    ?Tag $tag = null,
): AutomationRun {
    return $automation->runs()->create([
        'subscriber_id' => $subscriber->id,
        'status' => $status,
        'graph' => [
            'nodes' => [
                ['id' => 'trigger', 'type' => 'trigger', 'data' => ['kind' => AutomationTrigger::Subscribed->value]],
                ['id' => 'tag', 'type' => 'action', 'data' => ['kind' => AutomationAction::AddTag->value, 'tag_uuid' => $tag?->uuid]],
                ['id' => 'check', 'type' => 'condition', 'data' => ['kind' => AutomationCondition::HasTag->value]],
            ],
            'edges' => [],
        ],
        'current_node_id' => $status->isOpen() ? 'check' : null,
    ]);
}

test('the activity page lists runs with their step trail', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $automation = Automation::factory()->active()->for($team)->create();
    $tag = Tag::factory()->for($team)->create(['name' => 'VIP']);
    $subscriber = Subscriber::factory()
        ->for(Audience::factory()->for($team))
        ->create(['email' => 'ada@example.com', 'first_name' => 'Ada', 'last_name' => null]);

    $run = activityRun($automation, $subscriber, tag: $tag);
    $run->steps()->create([
        'node_id' => 'trigger',
        'status' => 'completed',
        'result' => ['trigger' => AutomationTrigger::Subscribed->value],
        'processed_at' => now()->subMinutes(3),
    ]);
    $run->steps()->create([
        'node_id' => 'tag',
        'status' => 'completed',
        'result' => ['tag_uuid' => $tag->uuid, 'added' => true],
        'processed_at' => now()->subMinutes(2),
    ]);
    $run->steps()->create([
        'node_id' => 'check',
        'status' => 'completed',
        'result' => ['matched' => false],
        'processed_at' => now()->subMinute(),
    ]);

    $this->actingAs($user)
        ->get(route('automations.activity', [$team, $automation]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('automations/activity')
            ->where('automation.name', $automation->name)
            ->where('canManage', true)
            ->has('runs.data', 1)
            ->where('runs.data.0.status', 'completed')
            ->where('runs.data.0.status_label', 'Completed')
            ->where('runs.data.0.subscriber.email', 'ada@example.com')
            ->has('runs.data.0.steps', 3)
            // Ordered by when each step ran, and labelled from the run's own graph.
            ->where('runs.data.0.steps.0.label', 'Someone subscribes')
            // The trigger result names the same trigger as the label, so the
            // detail is suppressed rather than printed twice.
            ->where('runs.data.0.steps.0.detail', null)
            ->where('runs.data.0.steps.1.label', 'Add tag')
            ->where('runs.data.0.steps.1.detail', 'VIP')
            ->where('runs.data.0.steps.2.label', 'Has tag')
            ->where('runs.data.0.steps.2.detail', 'Took the False branch'));
});

test('the activity page reports multiple active branches', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $automation = Automation::factory()->active()->for($team)->create();
    $subscriber = Subscriber::factory()->for(Audience::factory()->for($team))->create();
    $run = activityRun($automation, $subscriber, AutomationRunStatus::Pending);
    $run->steps()->create(['node_id' => 'tag', 'status' => 'pending']);
    $run->steps()->create(['node_id' => 'check', 'status' => 'waiting', 'scheduled_at' => now()->addHour()]);

    $this->actingAs($user)
        ->get(route('automations.activity', [$team, $automation]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('runs.data.0.current_step', '2 active branches')
            ->has('runs.data.0.steps', 2));
});

test('the summary counts every run by state', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $automation = Automation::factory()->active()->for($team)->create();
    $audience = Audience::factory()->for($team)->create();

    foreach ([
        AutomationRunStatus::Waiting,
        AutomationRunStatus::Completed,
        AutomationRunStatus::Completed,
        AutomationRunStatus::Failed,
    ] as $status) {
        activityRun(
            $automation,
            Subscriber::factory()->for($audience)->create(),
            $status,
        );
    }

    $this->actingAs($user)
        ->get(route('automations.activity', [$team, $automation]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('summary.enrolled', 4)
            ->where('summary.in_flight', 1)
            ->where('summary.completed', 2)
            ->where('summary.failed', 1));
});

test('runs can be filtered by status and searched by email', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $automation = Automation::factory()->active()->for($team)->create();
    $audience = Audience::factory()->for($team)->create();

    $waiting = Subscriber::factory()->for($audience)->create(['email' => 'waiting@example.com']);
    $done = Subscriber::factory()->for($audience)->create(['email' => 'done@example.com']);

    activityRun($automation, $waiting, AutomationRunStatus::Waiting);
    activityRun($automation, $done, AutomationRunStatus::Completed);

    $this->actingAs($user)
        ->get(route('automations.activity', [$team, $automation, 'status' => 'waiting']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('runs.data', 1)
            ->where('runs.data.0.subscriber.email', 'waiting@example.com')
            ->where('filters.status', 'waiting'));

    $this->actingAs($user)
        ->get(route('automations.activity', [$team, $automation, 'q' => 'done@']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('runs.data', 1)
            ->where('runs.data.0.subscriber.email', 'done@example.com'));

    // An unknown status is not a filter, so the full list comes back.
    $this->actingAs($user)
        ->get(route('automations.activity', [$team, $automation, 'status' => 'nonsense']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('runs.data', 2)
            ->where('filters.status', ''));
});

test('a step whose node was deleted from the graph still reads as a step', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $automation = Automation::factory()->active()->for($team)->create();
    $subscriber = Subscriber::factory()->for(Audience::factory()->for($team))->create();

    $run = activityRun($automation, $subscriber, AutomationRunStatus::Failed);
    $run->steps()->create([
        'node_id' => 'gone',
        'status' => 'failed',
        'result' => ['error' => 'The current step is missing from the saved graph.'],
        'processed_at' => now(),
    ]);

    $this->actingAs($user)
        ->get(route('automations.activity', [$team, $automation]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('runs.data.0.steps.0.label', 'Removed step')
            ->where('runs.data.0.steps.0.detail', 'The current step is missing from the saved graph.'));
});

test('activity for another team is not reachable', function () {
    $user = User::factory()->create();
    $automation = Automation::factory()->for(Team::factory()->create())->create();

    $this->actingAs($user)
        ->get(route('automations.activity', [$user->currentTeam, $automation]))
        ->assertNotFound();
});
