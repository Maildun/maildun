<?php

use App\Enums\EmailProvider;
use App\Models\Team;
use App\Models\TeamEmailIntegration;
use App\Models\TeamSender;
use App\Models\User;
use App\Services\TeamMailer;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;

test('the email delivery page exposes one safe connection without leaking secrets', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $sentinel = 'smtp-page-secret-never-return-this';
    $integration = TeamEmailIntegration::factory()->for($team)->smtp()->create([
        'settings' => [
            'host' => 'smtp.example.com',
            'port' => 587,
            'username' => 'mailer',
            'password' => $sentinel,
            'encryption' => 'tls',
        ],
    ]);

    $response = $this->actingAs($user)
        ->get(route('teams.email-provider.edit', $team))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('teams/email-provider')
            ->has('providers', 2)
            ->where('integration.uuid', $integration->uuid)
            ->where('integration.provider', 'smtp')
            ->where('integration.delivery_is_verified', true)
            ->where('integration.verified_sender_count', 1)
            ->where('canManage', true)
            ->missing('integration.settings.password'));

    $response->assertDontSee($sentinel, false);
});

test('a workspace can persist only one delivery connection', function () {
    $team = Team::factory()->create();
    TeamEmailIntegration::factory()->for($team)->smtp()->create();

    expect(fn () => TeamEmailIntegration::factory()->for($team)->ses()->create())
        ->toThrow(QueryException::class);
});

test('the connection endpoint rejects a second delivery provider', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    TeamEmailIntegration::factory()->for($team)->smtp()->create();

    $this->actingAs($user)
        ->post(route('teams.email-provider.store', $team), validSesConnectionPayload())
        ->assertSessionHasErrors('provider');

    expect($team->emailIntegration()->count())->toBe(1);
});

test('the connection endpoint accepts loopback SMTP hosts with local opt-in', function (string $hostname, int $port) {
    config()->set('mail.allow_local_smtp_hosts', true);
    $user = User::factory()->create();
    $team = $user->currentTeam;

    $this->actingAs($user)
        ->post(route('teams.email-provider.store', $team), validSmtpConnectionPayload([
            'smtp_host' => $hostname,
            'smtp_port' => $port,
        ]))
        ->assertSessionHasNoErrors();

    $integration = $team->emailIntegration()->firstOrFail();

    expect($integration->settings['host'])->toBe($hostname)
        ->and($integration->settings['port'])->toBe($port)
        ->and($integration->hasCompleteConfiguration())->toBeTrue();
})->with([
    'localhost with Mailpit default port' => ['localhost', 1025],
    'IPv4 loopback' => ['127.0.0.1', 2525],
    'IPv6 loopback' => ['::1', 1025],
]);

test('the connection endpoint rejects loopback SMTP hosts without local opt-in', function (string $hostname) {
    config()->set('mail.allow_local_smtp_hosts', false);
    $user = User::factory()->create();
    $team = $user->currentTeam;

    $this->actingAs($user)
        ->post(route('teams.email-provider.store', $team), validSmtpConnectionPayload([
            'smtp_host' => $hostname,
        ]))
        ->assertSessionHasErrors('smtp_host');

    expect($team->emailIntegration()->exists())->toBeFalse();
})->with([
    'localhost' => ['localhost'],
    'IPv4 loopback' => ['127.0.0.1'],
]);

test('a delivery test accepts explicit From and To addresses without a sender', function () {
    Queue::fake();
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $integration = TeamEmailIntegration::factory()->untested()->for($team)->smtp()->create();

    $this->actingAs($user)
        ->post(route('teams.email-provider.test', [$team, $integration]), [
            'from' => 'delivery@example.com',
            'to' => 'owner@example.com',
        ])
        ->assertSessionHasNoErrors();

    expect($team->senders()->exists())->toBeFalse();
});

test('changing credentials invalidates delivery and current sender proofs', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $integration = TeamEmailIntegration::factory()->for($team)->smtp()->create([
        'settings' => validSmtpSettings(),
    ]);
    $sender = TeamSender::factory()->for($team)->create(['email' => 'mail@example.com']);
    $version = $integration->verification_version;

    $this->actingAs($user)
        ->patch(route('teams.email-provider.update', [$team, $integration]), validSmtpConnectionPayload([
            'smtp_host' => 'smtp.changed.example.com',
        ]))
        ->assertSessionHasNoErrors();

    expect($integration->refresh()->verification_version)->toBe($version + 1)
        ->and($integration->last_tested_at)->toBeNull()
        ->and($integration->test_from_address)->toBeNull()
        ->and($sender->refresh()->isVerifiedFor($integration))->toBeFalse();
});

test('changing only the connection name preserves delivery and sender proofs', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $integration = TeamEmailIntegration::factory()->for($team)->smtp()->create([
        'settings' => validSmtpSettings(),
    ]);
    $sender = TeamSender::factory()->for($team)->create();
    $version = $integration->verification_version;

    $this->actingAs($user)
        ->patch(route('teams.email-provider.update', [$team, $integration]), validSmtpConnectionPayload([
            'name' => 'Renamed connection',
        ]))
        ->assertSessionHasNoErrors();

    expect($integration->refresh()->verification_version)->toBe($version)
        ->and($integration->isVerified())->toBeTrue()
        ->and($sender->refresh()->isVerifiedFor($integration))->toBeTrue();
});

test('enabling provider trust persists the setting', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $integration = TeamEmailIntegration::factory()->withoutSender()->for($team)->smtp()->create([
        'settings' => validSmtpSettings(),
        'trust_provider_senders' => false,
    ]);

    $this->actingAs($user)
        ->patch(route('teams.email-provider.update', [$team, $integration]), validSmtpConnectionPayload([
            'trust_provider_senders' => true,
        ]))
        ->assertSessionHasNoErrors();

    expect($integration->refresh()->trust_provider_senders)->toBeTrue();
});

test('disabling provider trust revokes only provider-authorized senders', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $integration = TeamEmailIntegration::factory()->withoutSender()->for($team)->smtp()->create([
        'settings' => validSmtpSettings(),
        'trust_provider_senders' => true,
    ]);
    $providerSender = TeamSender::factory()->pending()->for($team)->create([
        'email' => 'noreply@example.com',
    ]);
    $providerSender->authorizeFor($integration, trustedProvider: true);
    $mailboxSender = TeamSender::factory()->for($team)->create([
        'email' => 'support@example.com',
    ]);

    $this->actingAs($user)
        ->patch(route('teams.email-provider.update', [$team, $integration]), validSmtpConnectionPayload([
            'trust_provider_senders' => false,
        ]))
        ->assertSessionHasNoErrors();

    expect($providerSender->refresh()->email_verified_at)->toBeNull()
        ->and($providerSender->verified_by_provider)->toBeFalse()
        ->and($mailboxSender->refresh()->isVerifiedFor($integration->refresh()))->toBeTrue();
});

test('disconnecting delivery leaves senders registered but invalidates their proof', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $integration = TeamEmailIntegration::factory()->for($team)->smtp()->create();
    $sender = TeamSender::factory()->for($team)->create();

    $this->actingAs($user)
        ->delete(route('teams.email-provider.destroy', [$team, $integration]))
        ->assertRedirect(route('teams.email-provider.edit', $team));

    expect($team->emailIntegration()->exists())->toBeFalse()
        ->and($sender->refresh()->exists)->toBeTrue()
        ->and($sender->verified_email_integration_id)->toBeNull()
        ->and($sender->isVerified())->toBeFalse();
});

test('the single verified delivery connection resolves automatically', function (EmailProvider $provider) {
    $team = Team::factory()->create();
    TeamEmailIntegration::factory()->for($team)->create([
        'provider' => $provider,
        'settings' => $provider === EmailProvider::Smtp
            ? validSmtpSettings()
            : validSesSettings(),
    ]);

    expect(app(TeamMailer::class)->provider($team))->toBe($provider->value);
})->with(EmailProvider::supported());

test('members cannot access email delivery settings', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($owner, ['role' => 'owner']);
    $team->members()->attach($member, ['role' => 'member']);
    $integration = TeamEmailIntegration::factory()->for($team)->smtp()->create();

    $this->actingAs($member)
        ->get(route('teams.email-provider.show', [$team, $integration]))
        ->assertForbidden();

    $this->actingAs($member)
        ->patch(route('teams.email-provider.update', [$team, $integration]), validSmtpConnectionPayload())
        ->assertForbidden();
});

/** @return array<string, mixed> */
function validSmtpConnectionPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Primary SMTP',
        'provider' => 'smtp',
        'smtp_host' => 'smtp.example.com',
        'smtp_port' => 587,
        'smtp_username' => 'mailer',
        'smtp_password' => '',
        'smtp_encryption' => 'tls',
    ], $overrides);
}

/** @return array<string, mixed> */
function validSesConnectionPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Primary SES',
        'provider' => 'ses',
        'ses_region' => 'us-east-1',
        'ses_access_key_id' => 'AKIAIOSFODNN7EXAMPLE',
        'ses_secret_access_key' => 'sestestsecretXXXXXXXXXXXXXXXXXXXXXXXXXXX',
        'ses_configuration_set' => 'maildun-feedback',
        'ses_sns_topic_arn' => 'arn:aws:sns:us-east-1:123456789012:maildun-feedback',
    ], $overrides);
}

/** @return array<string, int|string|null> */
function validSmtpSettings(): array
{
    return [
        'host' => 'smtp.example.com',
        'port' => 587,
        'username' => 'mailer',
        'password' => 'secret',
        'encryption' => 'tls',
    ];
}

/** @return array<string, string> */
function validSesSettings(): array
{
    return [
        'region' => 'us-east-1',
        'access_key_id' => 'AKIAIOSFODNN7EXAMPLE',
        'secret_access_key' => 'sestestsecretXXXXXXXXXXXXXXXXXXXXXXXXXXX',
        'configuration_set' => 'maildun-feedback',
        'sns_topic_arn' => 'arn:aws:sns:us-east-1:123456789012:maildun-feedback',
    ];
}
