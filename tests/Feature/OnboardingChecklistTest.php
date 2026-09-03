<?php

use App\Models\Audience;
use App\Models\Automation;
use App\Models\Email;
use App\Models\Subscriber;
use App\Models\TeamEmailIntegration;
use App\Models\TeamSenderDomain;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('a new team starts with every getting started step incomplete', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('onboarding.completed', 0)
            ->where('onboarding.total', 6)
            ->where('onboarding.steps', [
                ['key' => 'delivery', 'completed' => false],
                ['key' => 'sender', 'completed' => false],
                ['key' => 'audience', 'completed' => false],
                ['key' => 'subscribers', 'completed' => false],
                ['key' => 'campaign', 'completed' => false],
                ['key' => 'automation', 'completed' => false],
            ]));
});

test('the checklist completes as the team sets up sending', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    TeamEmailIntegration::factory()->for($team)->create();

    $audience = Audience::factory()->create(['team_id' => $team->id]);
    Subscriber::factory()->create(['audience_id' => $audience->id]);
    Email::factory()->create(['team_id' => $team->id, 'sent_at' => now()]);
    Automation::factory()->create(['team_id' => $team->id]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('onboarding.completed', 6)
            ->where('onboarding.steps.0.completed', true)
            ->where('onboarding.steps.1.completed', true)
            ->where('onboarding.steps.2.completed', true)
            ->where('onboarding.steps.3.completed', true)
            ->where('onboarding.steps.4.completed', true)
            ->where('onboarding.steps.5.completed', true));
});

test('a tested delivery connection and a verified default sender complete separate steps', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    TeamEmailIntegration::factory()->withoutSender()->for($team)->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('onboarding.completed', 1)
            ->where('onboarding.steps.0', ['key' => 'delivery', 'completed' => true])
            ->where('onboarding.steps.1', ['key' => 'sender', 'completed' => false]));
});

test('adding the first sender through a verified domain completes the sender step', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $integration = TeamEmailIntegration::factory()->withoutSender()->for($team)->create();
    TeamSenderDomain::factory()->for($team)->create([
        'domain' => 'example.com',
        'verified_at' => now(),
        'verified_email_integration_id' => $integration->id,
        'verified_email_integration_version' => $integration->verification_version,
    ]);

    $this->actingAs($user)
        ->post(route('teams.sender.store', $team), [
            'name' => 'Newsletter',
            'email' => 'news@example.com',
        ])
        ->assertSessionHasNoErrors();

    $sender = $team->senders()->sole();

    expect($team->refresh()->active_sender_id)->toBe($sender->id);

    $this->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('onboarding.completed', 2)
            ->where('onboarding.steps.0', ['key' => 'delivery', 'completed' => true])
            ->where('onboarding.steps.1', ['key' => 'sender', 'completed' => true]));
});

test('a legacy from address does not complete the current sender setup', function () {
    $user = User::factory()->create();
    $user->currentTeam->update(['email_from_address' => 'hello@example.com']);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('onboarding.completed', 0)
            ->where('onboarding.steps.0.completed', false)
            ->where('onboarding.steps.1.completed', false));
});

test('a sender with stale verification does not complete the sender step', function () {
    $user = User::factory()->create();
    $integration = TeamEmailIntegration::factory()->for($user->currentTeam)->create();
    $integration->increment('verification_version');

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('onboarding.completed', 1)
            ->where('onboarding.steps.0.completed', true)
            ->where('onboarding.steps.1.completed', false));
});

test('a draft campaign does not complete the send step', function () {
    $user = User::factory()->create();
    Email::factory()->create(['team_id' => $user->currentTeam->id, 'sent_at' => null]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('onboarding.completed', 0)
            ->where('onboarding.steps.4.completed', false));
});

test('another team activity never completes the checklist', function () {
    $user = User::factory()->create();
    $otherAudience = Audience::factory()->create();

    Subscriber::factory()->create(['audience_id' => $otherAudience->id]);
    Email::factory()->create(['team_id' => $otherAudience->team_id, 'sent_at' => now()]);
    Automation::factory()->create(['team_id' => $otherAudience->team_id]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('onboarding.completed', 0));
});
