<?php

use App\Actions\Emails\BuildSendReadiness;
use App\Models\Audience;
use App\Models\Email;
use App\Models\Subscriber;
use App\Models\Team;
use App\Models\TeamEmailIntegration;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

function readyCampaign(Team $team): Email
{
    $audience = Audience::factory()->for($team)->create();
    Subscriber::factory()->for($audience)->create();

    return Email::factory()->for($team)->create([
        'audience_id' => $audience->id,
        'subject' => 'Hello',
        'html' => '<p>Hi</p>',
    ]);
}

/** @return array<string, bool> */
function readinessByKey(Email $email): array
{
    return collect(app(BuildSendReadiness::class)->handle($email)['checks'])
        ->mapWithKeys(fn (array $check): array => [$check['key'] => $check['passed']])
        ->all();
}

test('a campaign with a tested provider, verified sender, recipients and content is ready', function () {
    $team = Team::factory()->create();
    TeamEmailIntegration::factory()->for($team)->smtp()->create();

    $readiness = app(BuildSendReadiness::class)->handle(readyCampaign($team));

    expect($readiness['ready'])->toBeTrue();
});

test('a workspace without email delivery is not ready and links to the provider settings', function () {
    $team = Team::factory()->create();

    $checks = collect(app(BuildSendReadiness::class)->handle(readyCampaign($team))['checks'])->keyBy('key');

    expect($checks['provider']['passed'])->toBeFalse()
        ->and($checks['provider']['action_url'])->toBe(route('teams.email-provider.edit', $team))
        ->and($checks['sender']['passed'])->toBeFalse();
});

test('an unverified sender blocks the send and links to the sender settings', function () {
    $team = Team::factory()->create();
    TeamEmailIntegration::factory()->for($team)->smtp()->unverifiedSender()->create();

    $checks = collect(app(BuildSendReadiness::class)->handle(readyCampaign($team))['checks'])->keyBy('key');

    expect($checks['provider']['passed'])->toBeTrue()
        ->and($checks['sender']['passed'])->toBeFalse()
        ->and($checks['sender']['action_url'])->toBe(route('teams.sender.edit', $team));
});

test('recipients fail when nobody in the audience can be emailed', function () {
    $team = Team::factory()->create();
    TeamEmailIntegration::factory()->for($team)->smtp()->create();
    $audience = Audience::factory()->for($team)->create();
    Subscriber::factory()->for($audience)->unsubscribed()->create();
    $email = Email::factory()->for($team)->create([
        'audience_id' => $audience->id,
        'subject' => 'Hello',
        'html' => '<p>Hi</p>',
    ]);

    expect(readinessByKey($email))->toMatchArray(['recipients' => false, 'provider' => true]);
});

test('the setup hub receives the send readiness', function () {
    $user = User::factory()->create();
    $email = readyCampaign($user->currentTeam);

    $this->actingAs($user)
        ->get(route('emails.edit', [$user->currentTeam, $email]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('sendReadiness.ready', false)
            ->where('sendReadiness.checks.0.key', 'provider')
            ->where('sendReadiness.checks.0.passed', false));
});
