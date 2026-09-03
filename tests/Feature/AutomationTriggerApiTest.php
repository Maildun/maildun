<?php

use App\Enums\AutomationRunStatus;
use App\Enums\AutomationStatus;
use App\Enums\AutomationTrigger;
use App\Models\Audience;
use App\Models\Automation;
use App\Models\Subscriber;
use App\Models\Team;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

beforeEach(fn () => Queue::fake());

/** @return array{0: Automation, 1: Subscriber} */
function apiAutomation(?Team $team = null): array
{
    $team ??= Team::factory()->create();
    $tag = $team->tags()->create(['name' => 'From API']);

    // A step after the trigger, so a started run stays open instead of
    // completing on the spot.
    $automation = Automation::factory()
        ->active()
        ->triggeredBy(AutomationTrigger::Api)
        ->for($team)
        ->create([
            'graph' => [
                'nodes' => [
                    ['id' => 'trigger', 'type' => 'trigger', 'data' => ['kind' => AutomationTrigger::Api->value]],
                    ['id' => 'tag', 'type' => 'action', 'data' => ['kind' => 'add_tag', 'tag_uuid' => $tag->uuid]],
                ],
                'edges' => [['source' => 'trigger', 'target' => 'tag']],
            ],
        ]);
    $subscriber = Subscriber::factory()->for(Audience::factory()->for($team))->create();

    return [$automation, $subscriber];
}

test('a valid token starts a run for the matching subscriber', function () {
    [$automation, $subscriber] = apiAutomation();

    $this->postJson(
        route('automations.trigger', $automation),
        ['email' => $subscriber->email, 'data' => ['plan' => 'pro']],
        ['X-Automation-Token' => $automation->trigger_token],
    )
        ->assertStatus(202)
        ->assertJson([
            'status' => AutomationRunStatus::Pending->value,
            'automation' => $automation->uuid,
            'subscriber' => $subscriber->uuid,
        ]);

    $run = $automation->runs()->firstOrFail();

    expect($run->subscriber_id)->toBe($subscriber->id)
        ->and($run->context['source'])->toBe('api')
        ->and($run->context['data'])->toBe(['plan' => 'pro']);
});

test('a bearer token is accepted too', function () {
    [$automation, $subscriber] = apiAutomation();

    $this->postJson(
        route('automations.trigger', $automation),
        ['email' => $subscriber->email],
        ['Authorization' => 'Bearer '.$automation->trigger_token],
    )->assertStatus(202);
});

test('the email lookup is case insensitive', function () {
    [$automation, $subscriber] = apiAutomation();

    $this->postJson(
        route('automations.trigger', $automation),
        ['email' => Str::upper($subscriber->email)],
        ['X-Automation-Token' => $automation->trigger_token],
    )->assertStatus(202);
});

test('the email is trimmed before validation and lookup', function () {
    [$automation, $subscriber] = apiAutomation();

    $this->postJson(
        route('automations.trigger', $automation),
        ['email' => '  '.$subscriber->email.'  '],
        ['X-Automation-Token' => $automation->trigger_token],
    )->assertAccepted();
});

test('a missing or wrong token is rejected', function () {
    [$automation, $subscriber] = apiAutomation();

    $this->postJson(route('automations.trigger', $automation), ['email' => $subscriber->email])
        ->assertUnauthorized();

    $this->postJson(
        route('automations.trigger', $automation),
        ['email' => $subscriber->email],
        ['X-Automation-Token' => 'wrong-token'],
    )->assertUnauthorized();

    expect($automation->runs()->count())->toBe(0);
});

test('authentication runs before payload validation', function () {
    [$automation] = apiAutomation();

    $this->postJson(route('automations.trigger', $automation), ['email' => 'not-an-email'])
        ->assertUnauthorized()
        ->assertJsonMissingValidationErrors('email');
});

test('failed token guesses are rate limited', function () {
    [$automation, $subscriber] = apiAutomation();

    foreach (range(1, 60) as $attempt) {
        $this->postJson(
            route('automations.trigger', $automation),
            ['email' => $subscriber->email],
            ['X-Automation-Token' => 'wrong-token'],
        )->assertUnauthorized();
    }

    $this->postJson(
        route('automations.trigger', $automation),
        ['email' => $subscriber->email],
        ['X-Automation-Token' => 'wrong-token'],
    )->assertTooManyRequests();
});

test('another team token cannot trigger this automation', function () {
    [$automation, $subscriber] = apiAutomation();
    [$other] = apiAutomation();

    $this->postJson(
        route('automations.trigger', $automation),
        ['email' => $subscriber->email],
        ['X-Automation-Token' => $other->trigger_token],
    )->assertUnauthorized();
});

test('a malformed stored token is rejected instead of causing a server error', function () {
    [$automation, $subscriber] = apiAutomation();

    DB::table('automations')
        ->where('id', $automation->id)
        ->update(['trigger_token' => 'not-an-encrypted-token']);

    $this->postJson(
        route('automations.trigger', $automation),
        ['email' => $subscriber->email],
        ['X-Automation-Token' => 'some-token'],
    )->assertUnauthorized();
});

test('a paused or draft automation is not reachable', function () {
    [$automation, $subscriber] = apiAutomation();
    $automation->update(['status' => AutomationStatus::Paused]);

    $this->postJson(
        route('automations.trigger', $automation),
        ['email' => $subscriber->email],
        ['X-Automation-Token' => $automation->trigger_token],
    )->assertNotFound();
});

test('an automation on a different trigger is not reachable over the api', function () {
    $team = Team::factory()->create();
    $automation = Automation::factory()
        ->active()
        ->triggeredBy(AutomationTrigger::Subscribed)
        ->for($team)
        ->create();
    $subscriber = Subscriber::factory()->for(Audience::factory()->for($team))->create();

    $this->postJson(
        route('automations.trigger', $automation),
        ['email' => $subscriber->email],
        ['X-Automation-Token' => $automation->trigger_token],
    )->assertNotFound();
});

test('a subscriber from another team is never matched', function () {
    [$automation] = apiAutomation();
    $stranger = Subscriber::factory()->for(Audience::factory()->for(Team::factory()))->create();

    $this->postJson(
        route('automations.trigger', $automation),
        ['email' => $stranger->email],
        ['X-Automation-Token' => $automation->trigger_token],
    )->assertStatus(422);

    expect($automation->runs()->count())->toBe(0);
});

test('an audience is required when the email matches multiple subscribers', function () {
    [$automation, $subscriber] = apiAutomation();
    $otherAudience = Audience::factory()->for($automation->team)->create();
    Subscriber::factory()->for($otherAudience)->create(['email' => $subscriber->email]);

    $this->postJson(
        route('automations.trigger', $automation),
        ['email' => $subscriber->email],
        ['X-Automation-Token' => $automation->trigger_token],
    )
        ->assertUnprocessable()
        ->assertJsonValidationErrors('audience');

    $this->postJson(
        route('automations.trigger', $automation),
        ['email' => $subscriber->email, 'audience' => $subscriber->audience->uuid],
        ['X-Automation-Token' => $automation->trigger_token],
    )
        ->assertAccepted()
        ->assertJsonPath('subscriber', $subscriber->uuid);
});

test('validation failures use the standard json api error shape', function () {
    [$automation] = apiAutomation();

    $this->postJson(
        route('automations.trigger', $automation),
        ['email' => 'not-an-email', 'audience' => 'not-a-uuid'],
        ['X-Automation-Token' => $automation->trigger_token],
    )
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email', 'audience']);
});

test('a second call while a run is open is rejected', function () {
    [$automation, $subscriber] = apiAutomation();

    $this->postJson(
        route('automations.trigger', $automation),
        ['email' => $subscriber->email],
        ['X-Automation-Token' => $automation->trigger_token],
    )->assertStatus(202);

    $this->postJson(
        route('automations.trigger', $automation),
        ['email' => $subscriber->email],
        ['X-Automation-Token' => $automation->trigger_token],
    )->assertStatus(409);

    expect($automation->runs()->count())->toBe(1);
});
