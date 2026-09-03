<?php

use App\Enums\AutomationCondition;
use App\Enums\AutomationRunStatus;
use App\Enums\AutomationStatus;
use App\Enums\AutomationTrigger;
use App\Enums\TeamRole;
use App\Models\Audience;
use App\Models\Automation;
use App\Models\Subscriber;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;

test('the list shows the team automations with their open run counts', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $automation = Automation::factory()->active()->for($team)->create(['name' => 'Welcome series']);
    Automation::factory()->for(Team::factory()->create())->create(['name' => 'Other team']);

    $subscriber = Subscriber::factory()->for(Audience::factory()->for($team))->create();
    $automation->runs()->create(['subscriber_id' => $subscriber->id, 'status' => AutomationRunStatus::Waiting]);
    $automation->runs()->create(['subscriber_id' => $subscriber->id, 'status' => AutomationRunStatus::Completed]);

    $this->actingAs($user)
        ->get(route('automations.index', $team))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('automations/index')
            ->where('canManage', true)
            ->has('automations.data', 1)
            ->where('automations.data.0.name', 'Welcome series')
            ->where('automations.data.0.status', 'active')
            ->where('automations.data.0.enrolled_count', 2)
            ->where('automations.data.0.running_count', 1));
});

test('creating an automation lands on the editor with a draft graph', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;

    $this->actingAs($user)
        ->post(route('automations.store', $team), ['name' => 'Welcome series'])
        ->assertRedirect();

    $automation = $team->automations()->firstOrFail();

    expect($automation->name)->toBe('Welcome series')
        ->and($automation->status)->toBe(AutomationStatus::Draft)
        ->and($automation->graph['nodes'])->toHaveCount(1)
        ->and($automation->graph['nodes'][0]['type'])->toBe('trigger')
        ->and($automation->trigger_token)->not->toBeEmpty();
});

test('the editor offers Manual and Form for the source condition', function () {
    $user = User::factory()->create();
    $automation = Automation::factory()->for($user->currentTeam)->create();

    $this->actingAs($user)
        ->get(route('automations.edit', [$user->currentTeam, $automation]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('catalog.conditions.2', [
                'value' => AutomationCondition::Source->value,
                'label' => 'Source',
            ])
            ->where('catalog.sources', [
                ['value' => 'manual', 'label' => 'Manual'],
                ['value' => 'form', 'label' => 'Form'],
            ]));
});

test('a team can run more than one automation at a time', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;

    Automation::factory()->active()->for($team)->create(['name' => 'Welcome']);
    Automation::factory()->active()->for($team)->create(['name' => 'Win back']);
    Automation::factory()->paused()->for($team)->create(['name' => 'Paused one']);

    expect($team->automations()->where('status', AutomationStatus::Active)->count())->toBe(2);

    $this->actingAs($user)
        ->get(route('automations.index', $team))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('automations.data', 3));
});

test('an automation cannot be activated while its graph is incomplete', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    // The default graph is a lone trigger with nothing wired after it.
    $automation = Automation::factory()->for($team)->create();

    $this->actingAs($user)
        ->post(route('automations.activate', [$team, $automation]))
        ->assertSessionHasErrors('graph');

    expect($automation->refresh()->status)->toBe(AutomationStatus::Draft);
});

test('a source condition only accepts Manual or Form', function (?string $source) {
    $user = User::factory()->create();
    $automation = Automation::factory()->for($user->currentTeam)->create([
        'graph' => [
            'nodes' => [
                ['id' => 'trigger', 'type' => 'trigger', 'data' => ['kind' => AutomationTrigger::Subscribed->value]],
                ['id' => 'source', 'type' => 'condition', 'data' => [
                    'kind' => AutomationCondition::Source->value,
                    'source' => $source,
                ]],
            ],
            'edges' => [['source' => 'trigger', 'target' => 'source']],
        ],
    ]);

    $this->actingAs($user)
        ->post(route('automations.activate', [$user->currentTeam, $automation]))
        ->assertSessionHasErrors('graph');

    expect($automation->refresh()->status)->toBe(AutomationStatus::Draft);
})->with([
    'missing source' => null,
    'API source' => 'api',
]);

test('parallel branches cannot merge into one step', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $tag = $team->tags()->create(['name' => 'VIP']);
    $automation = Automation::factory()->for($team)->create([
        'graph' => [
            'nodes' => [
                ['id' => 'trigger', 'type' => 'trigger', 'data' => ['kind' => AutomationTrigger::Subscribed->value]],
                ['id' => 'first', 'type' => 'action', 'data' => ['kind' => 'add_tag', 'tag_uuid' => $tag->uuid]],
                ['id' => 'second', 'type' => 'action', 'data' => ['kind' => 'remove_tag', 'tag_uuid' => $tag->uuid]],
                ['id' => 'merged', 'type' => 'action', 'data' => ['kind' => 'add_tag', 'tag_uuid' => $tag->uuid]],
            ],
            'edges' => [
                ['source' => 'trigger', 'target' => 'first'],
                ['source' => 'trigger', 'target' => 'second'],
                ['source' => 'first', 'target' => 'merged'],
                ['source' => 'second', 'target' => 'merged'],
            ],
        ],
    ]);

    $this->actingAs($user)
        ->post(route('automations.activate', [$team, $automation]))
        ->assertSessionHasErrors([
            'graph' => 'Parallel branches cannot merge into the same step.',
        ]);
});

test('an automation can be activated and then paused', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $tag = $team->tags()->create(['name' => 'VIP']);
    $automation = Automation::factory()->for($team)->create([
        'graph' => [
            'nodes' => [
                ['id' => 'trigger', 'type' => 'trigger', 'data' => ['kind' => AutomationTrigger::Subscribed->value]],
                ['id' => 'tag', 'type' => 'action', 'data' => ['kind' => 'add_tag', 'tag_uuid' => $tag->uuid]],
            ],
            'edges' => [['source' => 'trigger', 'target' => 'tag']],
        ],
    ]);

    $this->actingAs($user)
        ->post(route('automations.activate', [$team, $automation]))
        ->assertRedirect();

    expect($automation->refresh()->status)->toBe(AutomationStatus::Active);

    $this->actingAs($user)
        ->post(route('automations.pause', [$team, $automation]))
        ->assertRedirect();

    expect($automation->refresh()->status)->toBe(AutomationStatus::Paused);
});

test('pausing an automation that is not active fails', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $automation = Automation::factory()->paused()->for($team)->create();

    $this->actingAs($user)
        ->post(route('automations.pause', [$team, $automation]))
        ->assertSessionHasErrors('status');
});

test('deleting an automation cancels the runs still open on it', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $automation = Automation::factory()->active()->for($team)->create();
    $subscriber = Subscriber::factory()->for(Audience::factory()->for($team))->create();

    $open = $automation->runs()->create(['subscriber_id' => $subscriber->id, 'status' => AutomationRunStatus::Waiting]);
    $done = $automation->runs()->create(['subscriber_id' => $subscriber->id, 'status' => AutomationRunStatus::Completed]);
    $openStep = $open->steps()->create(['node_id' => 'wait', 'status' => 'waiting', 'scheduled_at' => now()->addHour()]);
    $doneStep = $done->steps()->create(['node_id' => 'sent', 'status' => 'completed']);

    $this->actingAs($user)
        ->delete(route('automations.destroy', [$team, $automation]))
        ->assertRedirect(route('automations.index', $team));

    expect($automation->refresh()->trashed())->toBeTrue()
        ->and($open->refresh()->status)->toBe(AutomationRunStatus::Cancelled)
        ->and($open->failure_reason)->toBe('The automation was deleted.')
        ->and($openStep->refresh()->status)->toBe('cancelled')
        ->and($done->refresh()->status)->toBe(AutomationRunStatus::Completed)
        ->and($doneStep->refresh()->status)->toBe('completed');
});

test('regenerating the api token replaces it', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $automation = Automation::factory()->for($team)->create();
    $before = $automation->trigger_token;

    $this->actingAs($user)
        ->post(route('automations.regenerate-token', [$team, $automation]))
        ->assertRedirect();

    expect($automation->refresh()->trigger_token)->not->toBe($before)->not->toBeEmpty();
});

test('automations from another team are not reachable', function () {
    $user = User::factory()->create();
    $automation = Automation::factory()->for(Team::factory()->create())->create();

    $this->actingAs($user)
        ->get(route('automations.edit', [$user->currentTeam, $automation]))
        ->assertNotFound();
});

test('members can read automations but cannot change them', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);
    assignViewerRole($team, $member);
    $automation = Automation::factory()->active()->for($team)->create();

    $this->actingAs($member)
        ->get(route('automations.index', $team))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('canManage', false));

    $this->actingAs($member)
        ->post(route('automations.store', $team), ['name' => 'Nope'])
        ->assertForbidden();

    $this->actingAs($member)
        ->post(route('automations.pause', [$team, $automation]))
        ->assertForbidden();

    $this->actingAs($member)
        ->delete(route('automations.destroy', [$team, $automation]))
        ->assertForbidden();

    expect($automation->refresh()->status)->toBe(AutomationStatus::Active);
});

/** Trigger → one wired step, so a started run stays open. */
function lifecycleAutomation(Team $team, AutomationTrigger $trigger): Automation
{
    $tag = $team->tags()->create(['name' => 'Tagged '.fake()->unique()->word()]);

    return Automation::factory()->active()->for($team)->create([
        'trigger' => $trigger,
        'graph' => [
            'nodes' => [
                ['id' => 'trigger', 'type' => 'trigger', 'data' => ['kind' => $trigger->value]],
                ['id' => 'tag', 'type' => 'action', 'data' => ['kind' => 'add_tag', 'tag_uuid' => $tag->uuid]],
            ],
            'edges' => [['source' => 'trigger', 'target' => 'tag']],
        ],
    ]);
}

test('adding a subscriber starts the subscribed automations', function () {
    Queue::fake();

    $user = User::factory()->create();
    $team = $user->currentTeam;
    $audience = Audience::factory()->for($team)->create();

    $subscribed = lifecycleAutomation($team, AutomationTrigger::Subscribed);
    $unsubscribed = lifecycleAutomation($team, AutomationTrigger::Unsubscribed);
    $paused = lifecycleAutomation($team, AutomationTrigger::Subscribed);
    $paused->update(['status' => AutomationStatus::Paused]);

    $this->actingAs($user)
        ->post(route('audiences.subscribers.store', [$team, $audience]), ['email' => 'new@example.com', 'consent_confirmed' => true])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    expect($subscribed->runs()->count())->toBe(1)
        ->and($unsubscribed->runs()->count())->toBe(0)
        ->and($paused->runs()->count())->toBe(0);

    expect($subscribed->runs()->firstOrFail()->status)->toBe(AutomationRunStatus::Pending);
});

test('unsubscribing starts the unsubscribed automations', function () {
    Queue::fake();

    $user = User::factory()->create();
    $team = $user->currentTeam;
    $audience = Audience::factory()->for($team)->create();
    $subscriber = Subscriber::factory()->for($audience)->create();

    $automation = lifecycleAutomation($team, AutomationTrigger::Unsubscribed);

    $this->actingAs($user)
        ->patch(route('audiences.subscribers.unsubscribe', [$team, $audience, $subscriber]))
        ->assertRedirect();

    expect($automation->runs()->count())->toBe(1)
        ->and($automation->runs()->firstOrFail()->subscriber_id)->toBe($subscriber->id);
});

test('an automation scoped to one audience ignores the others', function () {
    Queue::fake();

    $user = User::factory()->create();
    $team = $user->currentTeam;
    $wanted = Audience::factory()->for($team)->create();
    $other = Audience::factory()->for($team)->create();

    $automation = lifecycleAutomation($team, AutomationTrigger::Subscribed);
    $automation->update(['trigger_config' => ['audience_uuid' => $wanted->uuid, 'tag_uuid' => null]]);

    $this->actingAs($user)
        ->post(route('audiences.subscribers.store', [$team, $other]), ['email' => 'ignored@example.com', 'consent_confirmed' => true])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    expect($automation->runs()->count())->toBe(0);

    $this->actingAs($user)
        ->post(route('audiences.subscribers.store', [$team, $wanted]), ['email' => 'wanted@example.com', 'consent_confirmed' => true])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    expect($automation->runs()->count())->toBe(1);
});

test('an automation never enrolls subscribers from another team', function () {
    Queue::fake();

    $user = User::factory()->create();
    $team = $user->currentTeam;
    $automation = lifecycleAutomation($team, AutomationTrigger::Subscribed);

    $stranger = User::factory()->create();
    $strangerAudience = Audience::factory()->for($stranger->currentTeam)->create();

    $this->actingAs($stranger)
        ->post(route('audiences.subscribers.store', [$stranger->currentTeam, $strangerAudience]), ['email' => 'stranger@example.com', 'consent_confirmed' => true])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    expect($automation->runs()->count())->toBe(0);
});

test('the api token is hidden from members who cannot manage the automation', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);
    assignViewerRole($team, $member);
    $automation = Automation::factory()->for($team)->create();

    $this->actingAs($member)
        ->get(route('automations.edit', [$team, $automation]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('automations/edit')
            ->where('automation.trigger_token', null));

    $this->actingAs($owner)
        ->get(route('automations.edit', [$team, $automation]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('automation.trigger_token', $automation->trigger_token));
});

test('saving an active automation rejects a graph that would not activate', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $tag = $team->tags()->create(['name' => 'VIP']);
    $automation = Automation::factory()->active()->for($team)->create([
        'graph' => [
            'nodes' => [
                ['id' => 'trigger', 'type' => 'trigger', 'position' => ['x' => 0, 'y' => 0], 'data' => ['kind' => AutomationTrigger::Subscribed->value]],
                ['id' => 'tag', 'type' => 'action', 'position' => ['x' => 0, 'y' => 120], 'data' => ['kind' => 'add_tag', 'tag_uuid' => $tag->uuid]],
            ],
            'edges' => [['id' => 'e1', 'source' => 'trigger', 'target' => 'tag']],
        ],
    ]);

    // A lone trigger passes UpdateAutomationRequest but could never be activated,
    // so an Active automation must not be edited into it.
    $this->actingAs($user)
        ->put(route('automations.update', [$team, $automation]), [
            'name' => $automation->name,
            'description' => null,
            'graph' => [
                'nodes' => [
                    ['id' => 'trigger', 'type' => 'trigger', 'position' => ['x' => 0, 'y' => 0], 'data' => ['kind' => AutomationTrigger::Subscribed->value]],
                ],
                'edges' => [],
            ],
        ])
        ->assertSessionHasErrors('graph');

    expect($automation->refresh()->graph['nodes'])->toHaveCount(2);
});

test('saving a draft automation accepts an incomplete graph', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $automation = Automation::factory()->for($team)->create();

    $this->actingAs($user)
        ->put(route('automations.update', [$team, $automation]), [
            'name' => 'Work in progress',
            'description' => null,
            'graph' => [
                'nodes' => [
                    ['id' => 'trigger', 'type' => 'trigger', 'position' => ['x' => 0, 'y' => 0], 'data' => ['kind' => AutomationTrigger::Subscribed->value]],
                ],
                'edges' => [],
            ],
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($automation->refresh()->name)->toBe('Work in progress')
        ->and($automation->graph['nodes'])->toHaveCount(1);
});
