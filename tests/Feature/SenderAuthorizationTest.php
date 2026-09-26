<?php

use App\Actions\Emails\BuildTrackedEmailHtml;
use App\Actions\Emails\RetryEmailDeliveries;
use App\Enums\EmailDeliveryStatus;
use App\Enums\EmailStatus;
use App\Exceptions\EmailTransportException;
use App\Jobs\SendEmailDelivery;
use App\Jobs\SendTeamEmailIntegrationTest;
use App\Mail\CampaignEmail;
use App\Models\Audience;
use App\Models\Email;
use App\Models\Subscriber;
use App\Models\Team;
use App\Models\TeamApiKey;
use App\Models\TeamEmailIntegration;
use App\Models\TeamSender;
use App\Models\TransactionalEmail;
use App\Models\User;
use App\Services\SesFeedbackVerifier;
use App\Services\TeamMailer;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;

/**
 * @return array{0: User, 1: Team, 2: TeamEmailIntegration, 3: TeamSender}
 */
function workspaceAuthorizedFor(string $fromAddress): array
{
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $integration = TeamEmailIntegration::factory()->for($team)->ses()->create();
    $sender = TeamSender::factory()->for($team)->create(['email' => $fromAddress]);

    $team->forceFill([
        'active_sender_id' => $sender->id,
        'email_from_name' => $sender->name,
        'email_from_address' => $sender->email,
        'email_reply_to' => $sender->reply_to,
    ])->save();

    expect($sender->refresh()->isVerifiedFor($integration))->toBeTrue();

    return [$user, $team->fresh(), $integration, $sender];
}

function campaignSendingFrom(Team $team, ?string $fromAddress): Email
{
    $audience = Audience::factory()->for($team)->create(['from_address' => null]);
    Subscriber::factory()->for($audience)->create();

    return Email::factory()->for($team)->create([
        'audience_id' => $audience->id,
        'subject' => 'Hello',
        'html' => '<p>Hi</p>',
        'from_address' => $fromAddress,
    ]);
}

test('a campaign is refused before any recipient is queued when its From address is not verified for the connection', function () {
    Bus::fake();
    [$user, $team] = workspaceAuthorizedFor('hello@acme.test');
    $email = campaignSendingFrom($team, 'newsletter@unrelated-domain.test');

    $this->actingAs($user)
        ->post(route('emails.send', [$team, $email]))
        ->assertSessionHasErrors('email');

    expect($email->fresh()->status)->toBe(EmailStatus::Draft)
        ->and($email->deliveries()->count())->toBe(0);

    Bus::assertNothingBatched();
});

test('a sibling address on a verified sender domain is rejected until that exact address is verified', function () {
    Bus::fake();
    [$user, $team] = workspaceAuthorizedFor('hello@acme.test');
    $email = campaignSendingFrom($team, 'newsletter@acme.test');

    $this->actingAs($user)
        ->post(route('emails.send', [$team, $email]))
        ->assertSessionHasErrors('email');

    expect($email->fresh()->status)->toBe(EmailStatus::Draft);
    Bus::assertNothingBatched();
});

test('an audience sender override must be an exact verified workspace sender', function () {
    [$user, $team] = workspaceAuthorizedFor('hello@acme.test');
    $audience = Audience::factory()->for($team)->create(['from_address' => null]);

    $this->actingAs($user)
        ->patch(route('audiences.update', [$team, $audience]), ['from_address' => 'blast@acme.test'])
        ->assertSessionHasErrors('from_address');

    TeamSender::factory()->for($team)->create(['email' => 'blast@acme.test']);

    $this->actingAs($user)
        ->patch(route('audiences.update', [$team, $audience]), ['from_address' => 'blast@acme.test'])
        ->assertSessionHasNoErrors();

    expect($audience->fresh()->from_address)->toBe('blast@acme.test');
});

test('a campaign sender override that is not verified for the connection is rejected on save', function () {
    [$user, $team] = workspaceAuthorizedFor('hello@acme.test');
    $email = campaignSendingFrom($team, null);
    $sender = TeamSender::factory()->pending()->for($team)->create(['email' => 'blast@acme.test']);

    $this->actingAs($user)
        ->patch(route('emails.update', [$team, $email]), [
            'name' => $email->name,
            'subject' => 'Hello',
            'html' => '<p>Hi</p>',
            'sender_uuid' => $sender->uuid,
        ])
        ->assertSessionHasErrors('sender_uuid');
});

test('a transactional sender override that is not verified for the connection is rejected on save', function () {
    [$user, $team] = workspaceAuthorizedFor('hello@acme.test');
    $email = TransactionalEmail::factory()->for($team)->create(['from_address' => null]);

    $this->actingAs($user)
        ->patch(route('transactional_emails.update', [$team, $email]), [
            'name' => $email->name,
            'slug' => $email->slug,
            'subject' => 'Receipt',
            'html' => '<p>Thanks</p>',
            'from_address' => 'receipts@acme.test',
        ])
        ->assertSessionHasErrors('from_address');
});

test('a delivery job refuses a sender proof from an older connection version', function () {
    Mail::fake();
    [, $team, $integration] = workspaceAuthorizedFor('hello@acme.test');
    $email = campaignSendingFrom($team, 'hello@acme.test');
    $email->forceFill(['status' => EmailStatus::Sending])->save();
    $delivery = $email->deliveries()->create([
        'email_address' => 'reader@example.com',
        'status' => EmailDeliveryStatus::Queued,
        'provider' => 'ses',
        'uses_team_email_integration' => true,
    ]);

    $integration->forceFill([
        'verification_version' => $integration->verification_version + 1,
        'last_tested_at' => now(),
    ])->save();

    expect(fn () => (new SendEmailDelivery($delivery->id))->handle(
        app(BuildTrackedEmailHtml::class),
        app(TeamMailer::class),
    ))->toThrow(EmailTransportException::class);

    Mail::assertNotSent(CampaignEmail::class);
});

test('retrying refuses a campaign whose sender proof is stale', function () {
    Bus::fake();
    [, $team, $integration] = workspaceAuthorizedFor('hello@acme.test');
    $email = campaignSendingFrom($team, 'hello@acme.test');
    $email->forceFill(['status' => EmailStatus::Failed, 'recipient_count' => 1])->save();
    $email->deliveries()->create([
        'email_address' => 'reader@example.com',
        'status' => EmailDeliveryStatus::Failed,
        'provider' => 'ses',
        'uses_team_email_integration' => true,
    ]);
    $integration->increment('verification_version');

    expect(fn () => app(RetryEmailDeliveries::class)->handle($email->fresh()))
        ->toThrow(ValidationException::class);

    Bus::assertNothingBatched();
});

test('the transactional API refuses an unverified workspace sender without queueing', function () {
    Queue::fake();
    [, $team] = workspaceAuthorizedFor('hello@acme.test');
    $email = TransactionalEmail::factory()->for($team)->published()->create([
        'from_address' => 'receipts@acme.test',
    ]);
    $issued = TeamApiKey::issue($team, 'Production');

    $this->withToken($issued['token'])
        ->postJson(route('api.v1.transactional-emails.send', $email->slug), ['to' => 'ada@example.com'])
        ->assertStatus(503);

    Queue::assertNothingPushed();
    expect($team->transactionalEmailDeliveries()->count())->toBe(0);
});

test('a workspace without a verified delivery connection cannot queue campaign email', function () {
    Bus::fake();
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $email = campaignSendingFrom($team, 'anything@whatever.test');

    $this->actingAs($user)
        ->post(route('emails.send', [$team, $email]))
        ->assertSessionHasErrors('email');

    expect($email->fresh()->status)->toBe(EmailStatus::Draft);
    Bus::assertNothingBatched();
});

test('a first provider test uses explicit addresses without a registered workspace sender', function () {
    Mail::fake();
    Queue::fake();
    fakeSesFeedbackVerification();
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $integration = TeamEmailIntegration::factory()->untested()->for($team)->ses()->create();

    $this->actingAs($user)
        ->post(route('teams.email-provider.test', [$team, $integration]), [
            'from' => 'Delivery@Test.Example.com',
            'to' => 'reviewer@example.com',
        ])
        ->assertSessionHasNoErrors();

    $job = new SendTeamEmailIntegrationTest(
        $team->id,
        $integration->id,
        'reviewer@example.com',
        'delivery@test.example.com',
        hash('sha256', serialize([
            $integration->provider->value,
            $integration->settings,
        ])),
    );
    $job->handle(app(TeamMailer::class), app(SesFeedbackVerifier::class));

    expect($integration->fresh()->last_tested_at)->not->toBeNull()
        ->and($integration->fresh()->test_from_address)->toBe('delivery@test.example.com')
        ->and($team->senders()->exists())->toBeFalse();
});
