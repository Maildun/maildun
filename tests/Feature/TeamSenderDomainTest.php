<?php

use App\Contracts\DnsRecordLookup;
use App\Enums\EmailStatus;
use App\Jobs\SendTeamSenderVerification;
use App\Models\Audience;
use App\Models\Email;
use App\Models\Subscriber;
use App\Models\TeamEmailIntegration;
use App\Models\TeamSender;
use App\Models\TeamSenderDomain;
use App\Models\User;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Queue;

/** @param list<array<string, mixed>> $records */
function fakeSenderDomainDnsRecords(array $records): void
{
    app()->instance(DnsRecordLookup::class, new class($records) implements DnsRecordLookup
    {
        /** @param list<array<string, mixed>> $records */
        public function __construct(private readonly array $records) {}

        /** @return list<array<string, mixed>> */
        public function lookup(string $hostname): array
        {
            return $this->records;
        }
    });
}

test('a manager can register a normalized sender domain after delivery is verified', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    TeamEmailIntegration::factory()->withoutSender()->for($team)->ses()->create();

    $this->actingAs($user)
        ->post(route('teams.sender.domains.store', $team), [
            'domain' => ' Example.COM. ',
        ])
        ->assertSessionHasNoErrors();

    $senderDomain = $team->senderDomains()->sole();

    expect($senderDomain->domain)->toBe('example.com')
        ->and($senderDomain->dnsRecordName())->toBe('_maildun-verification.example.com')
        ->and($senderDomain->dnsRecordValue())->toStartWith('maildun-verification=')
        ->and($senderDomain->verification_token)->toHaveLength(48)
        ->and($senderDomain->verified_at)->toBeNull();
});

test('sender domains require a valid public-style domain and verified delivery', function (string $domain) {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    TeamEmailIntegration::factory()->withoutSender()->untested()->for($team)->ses()->create();

    $this->actingAs($user)
        ->post(route('teams.sender.domains.store', $team), ['domain' => $domain])
        ->assertSessionHasErrors('domain');

    expect($team->senderDomains()->exists())->toBeFalse();
})->with([
    'email address' => 'noreply@example.com',
    'url' => 'https://example.com',
    'single label' => 'localhost',
]);

test('a matching DNS TXT record authorizes registered senders for the current delivery connection', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $integration = TeamEmailIntegration::factory()->withoutSender()->for($team)->ses()->create();
    $senderDomain = TeamSenderDomain::factory()->for($team)->create([
        'domain' => 'example.com',
        'verification_token' => 'domain-verification-token',
    ]);
    $sender = TeamSender::factory()->pending()->for($team)->create([
        'email' => 'noreply@example.com',
    ]);
    fakeSenderDomainDnsRecords([
        [
            'type' => 'TXT',
            'host' => '_maildun-verification.example.com',
            'txt' => 'maildun-verification=domain-verification-token',
        ],
    ]);

    $this->actingAs($user)
        ->post(route('teams.sender.domains.verify', [$team, $senderDomain]))
        ->assertSessionHasNoErrors();

    expect($senderDomain->refresh()->isVerifiedFor($integration))->toBeTrue()
        ->and($sender->refresh()->isVerifiedFor($integration))->toBeTrue()
        ->and($sender->verified_sender_domain_id)->toBe($senderDomain->id)
        ->and($team->refresh()->active_sender_id)->toBe($sender->id);
});

test('a missing DNS TXT record leaves the domain and sender pending', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $integration = TeamEmailIntegration::factory()->withoutSender()->for($team)->ses()->create();
    $senderDomain = TeamSenderDomain::factory()->for($team)->create(['domain' => 'example.com']);
    $sender = TeamSender::factory()->pending()->for($team)->create([
        'email' => 'noreply@example.com',
    ]);
    fakeSenderDomainDnsRecords([
        ['type' => 'TXT', 'txt' => 'maildun-verification=wrong-token'],
    ]);

    $this->actingAs($user)
        ->post(route('teams.sender.domains.verify', [$team, $senderDomain]))
        ->assertSessionHasNoErrors();

    expect($senderDomain->refresh()->isVerifiedFor($integration))->toBeFalse()
        ->and($senderDomain->verification_checked_at)->not->toBeNull()
        ->and($sender->refresh()->isVerifiedFor($integration))->toBeFalse();
});

test('rechecking a verified domain restores an authorized sender as the workspace default', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $integration = TeamEmailIntegration::factory()->withoutSender()->for($team)->ses()->create();
    $senderDomain = TeamSenderDomain::factory()->for($team)->create([
        'domain' => 'example.com',
        'verification_token' => 'domain-verification-token',
        'verified_at' => now(),
        'verified_email_integration_id' => $integration->id,
        'verified_email_integration_version' => $integration->verification_version,
    ]);
    $sender = TeamSender::factory()->pending()->for($team)->create([
        'email' => 'noreply@example.com',
    ]);
    $sender->authorizeFor($integration, $senderDomain);
    fakeSenderDomainDnsRecords([
        [
            'type' => 'TXT',
            'host' => '_maildun-verification.example.com',
            'txt' => 'maildun-verification=domain-verification-token',
        ],
    ]);

    expect($team->active_sender_id)->toBeNull();

    $this->actingAs($user)
        ->post(route('teams.sender.domains.verify', [$team, $senderDomain]))
        ->assertSessionHasNoErrors();

    expect($team->refresh()->active_sender_id)->toBe($sender->id);
});

test('DNS verification requires a successful provider test from the same domain', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    TeamEmailIntegration::factory()->withoutSender()->for($team)->ses()->create([
        'test_from_address' => 'delivery@another-domain.test',
    ]);
    $senderDomain = TeamSenderDomain::factory()->for($team)->create([
        'domain' => 'example.com',
        'verification_token' => 'domain-verification-token',
    ]);
    fakeSenderDomainDnsRecords([
        ['type' => 'TXT', 'txt' => 'maildun-verification=domain-verification-token'],
    ]);

    $this->actingAs($user)
        ->post(route('teams.sender.domains.verify', [$team, $senderDomain]))
        ->assertSessionHasErrors('domain');

    expect($senderDomain->refresh()->verified_at)->toBeNull();
});

test('adding a sender on a verified domain authorizes it without queueing mailbox verification', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $integration = TeamEmailIntegration::factory()->withoutSender()->for($team)->ses()->create();
    $senderDomain = TeamSenderDomain::factory()->for($team)->create([
        'domain' => 'example.com',
        'verified_at' => now(),
        'verified_email_integration_id' => $integration->id,
        'verified_email_integration_version' => $integration->verification_version,
    ]);
    Queue::fake([SendTeamSenderVerification::class]);

    $this->actingAs($user)
        ->post(route('teams.sender.store', $team), [
            'name' => 'Automated mail',
            'email' => 'noreply@example.com',
            'reply_to' => 'support@example.com',
        ])
        ->assertSessionHasNoErrors();

    $sender = $team->senders()->sole();

    expect($sender->isVerifiedFor($integration))->toBeTrue()
        ->and($sender->verified_sender_domain_id)->toBe($senderDomain->id)
        ->and($team->refresh()->active_sender_id)->toBe($sender->id);
    Queue::assertNotPushed(SendTeamSenderVerification::class);
});

test('a trusted provider authorizes a registered sender on the tested domain without mailbox verification', function () {
    Queue::fake([SendTeamSenderVerification::class]);
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $integration = TeamEmailIntegration::factory()->withoutSender()->for($team)->smtp()->create([
        'trust_provider_senders' => true,
        'test_from_address' => 'delivery@example.com',
    ]);

    $this->actingAs($user)
        ->post(route('teams.sender.store', $team), [
            'name' => 'Automated mail',
            'email' => 'noreply@example.com',
            'reply_to' => 'support@example.com',
        ])
        ->assertSessionHasNoErrors();

    $sender = $team->senders()->sole();

    expect($sender->isVerifiedFor($integration))->toBeTrue()
        ->and($sender->verified_by_provider)->toBeTrue()
        ->and($sender->verified_sender_domain_id)->toBeNull();
    Queue::assertNotPushed(SendTeamSenderVerification::class);
});

test('a trusted provider does not authorize a sender outside the tested domain', function () {
    Queue::fake([SendTeamSenderVerification::class]);
    $user = User::factory()->create();
    $team = $user->currentTeam;
    TeamEmailIntegration::factory()->withoutSender()->for($team)->smtp()->create([
        'trust_provider_senders' => true,
        'test_from_address' => 'delivery@example.com',
    ]);

    $this->actingAs($user)
        ->post(route('teams.sender.store', $team), [
            'name' => 'Automated mail',
            'email' => 'noreply@another-domain.test',
        ])
        ->assertSessionHasNoErrors();

    expect($team->senders()->sole()->email_verified_at)->toBeNull();
    Queue::assertPushed(SendTeamSenderVerification::class);
});

test('a verified domain does not authorize an unregistered sibling address at send time', function () {
    Bus::fake();
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $integration = TeamEmailIntegration::factory()->withoutSender()->for($team)->ses()->create();
    $senderDomain = TeamSenderDomain::factory()->for($team)->create([
        'domain' => 'example.com',
        'verified_at' => now(),
        'verified_email_integration_id' => $integration->id,
        'verified_email_integration_version' => $integration->verification_version,
    ]);
    $sender = TeamSender::factory()->pending()->for($team)->create([
        'email' => 'noreply@example.com',
    ]);
    $sender->authorizeFor($integration, $senderDomain);
    $audience = Audience::factory()->for($team)->create(['from_address' => null]);
    Subscriber::factory()->for($audience)->create();
    $email = Email::factory()->for($team)->create([
        'audience_id' => $audience->id,
        'from_address' => 'billing@example.com',
    ]);

    $this->actingAs($user)
        ->post(route('emails.send', [$team, $email]))
        ->assertSessionHasErrors('email');

    expect($email->fresh()->status)->toBe(EmailStatus::Draft);
    Bus::assertNothingBatched();
});

test('changing the delivery connection version invalidates domain and sender proofs', function () {
    $team = User::factory()->create()->currentTeam;
    $integration = TeamEmailIntegration::factory()->withoutSender()->for($team)->ses()->create();
    $senderDomain = TeamSenderDomain::factory()->for($team)->create([
        'domain' => 'example.com',
        'verified_at' => now(),
        'verified_email_integration_id' => $integration->id,
        'verified_email_integration_version' => $integration->verification_version,
    ]);
    $sender = TeamSender::factory()->pending()->for($team)->create([
        'email' => 'noreply@example.com',
    ]);
    $sender->authorizeFor($integration, $senderDomain);

    $integration->forceFill([
        'verification_version' => $integration->verification_version + 1,
        'last_tested_at' => null,
    ])->save();

    expect($senderDomain->refresh()->isVerifiedFor($integration->refresh()))->toBeFalse()
        ->and($sender->refresh()->isVerifiedFor($integration))->toBeFalse()
        ->and($integration->isSenderAuthorizedFor('noreply@example.com'))->toBeFalse();
});

test('retesting delivery from another domain invalidates only DNS-authorized senders', function () {
    $team = User::factory()->create()->currentTeam;
    $integration = TeamEmailIntegration::factory()->withoutSender()->for($team)->ses()->create();
    $senderDomain = TeamSenderDomain::factory()->for($team)->create([
        'domain' => 'example.com',
        'verified_at' => now(),
        'verified_email_integration_id' => $integration->id,
        'verified_email_integration_version' => $integration->verification_version,
    ]);
    $domainSender = TeamSender::factory()->pending()->for($team)->create([
        'email' => 'noreply@example.com',
    ]);
    $domainSender->authorizeFor($integration, $senderDomain);
    $mailboxSender = TeamSender::factory()->for($team)->create([
        'email' => 'support@example.com',
    ]);

    $integration->forceFill([
        'last_tested_at' => now(),
        'test_from_address' => 'delivery@another-domain.test',
    ])->save();

    expect($domainSender->refresh()->isVerifiedFor($integration->refresh()))->toBeFalse()
        ->and($integration->isSenderAuthorizedFor('noreply@example.com'))->toBeFalse()
        ->and($mailboxSender->refresh()->isVerifiedFor($integration))->toBeTrue()
        ->and($integration->isSenderAuthorizedFor('support@example.com'))->toBeTrue();
});

test('removing a domain revokes only senders authorized through that domain', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $integration = TeamEmailIntegration::factory()->withoutSender()->for($team)->ses()->create();
    $senderDomain = TeamSenderDomain::factory()->for($team)->create([
        'domain' => 'example.com',
        'verified_at' => now(),
        'verified_email_integration_id' => $integration->id,
        'verified_email_integration_version' => $integration->verification_version,
    ]);
    $domainSender = TeamSender::factory()->pending()->for($team)->create([
        'email' => 'noreply@example.com',
    ]);
    $domainSender->authorizeFor($integration, $senderDomain);
    $mailboxSender = TeamSender::factory()->for($team)->create([
        'email' => 'support@example.com',
    ]);

    $this->actingAs($user)
        ->delete(route('teams.sender.domains.destroy', [$team, $senderDomain]))
        ->assertSessionHasNoErrors();

    expect($domainSender->refresh()->isVerifiedFor($integration))->toBeFalse()
        ->and($domainSender->verified_sender_domain_id)->toBeNull()
        ->and($mailboxSender->refresh()->isVerifiedFor($integration))->toBeTrue()
        ->and($team->senderDomains()->exists())->toBeFalse();
});

test('sender domain actions do not reveal another workspace domain', function () {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();
    $senderDomain = TeamSenderDomain::factory()->for($otherUser->currentTeam)->create();
    TeamEmailIntegration::factory()->withoutSender()->for($owner->currentTeam)->ses()->create();
    fakeSenderDomainDnsRecords([]);

    $this->actingAs($owner)
        ->post(route('teams.sender.domains.verify', [$owner->currentTeam, $senderDomain]))
        ->assertNotFound();

    $this->actingAs($owner)
        ->delete(route('teams.sender.domains.destroy', [$owner->currentTeam, $senderDomain]))
        ->assertNotFound();
});
