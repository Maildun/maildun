<?php

use App\Actions\Emails\BuildTrackedEmailHtml;
use App\Actions\Emails\RecordEmailAddressHealth;
use App\Enums\EmailAddressHealthReason;
use App\Enums\EmailAddressHealthStatus;
use App\Enums\EmailDeliveryStatus;
use App\Enums\EmailFailureCode;
use App\Enums\EmailProvider;
use App\Jobs\SendEmailDelivery;
use App\Jobs\SendTransactionalEmailDelivery;
use App\Models\Email;
use App\Models\EmailAddressHealth;
use App\Models\EmailDelivery;
use App\Models\Team;
use App\Models\TeamApiKey;
use App\Models\TeamEmailIntegration;
use App\Models\TeamSender;
use App\Models\TransactionalEmail;
use App\Models\TransactionalEmailDelivery;
use App\Services\TeamMailer;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;

test('permanent feedback suppresses a normalized address and suppression remains sticky', function () {
    $team = Team::factory()->create();
    $healthRecorder = app(RecordEmailAddressHealth::class);
    $bouncedAt = now();

    $healthRecorder->recordTransientBounce(
        $team,
        ' User@Example.COM ',
        EmailProvider::AmazonSes,
        $bouncedAt->copy()->subMinute(),
        'MailboxFull',
    );
    $healthRecorder->recordPermanentBounce(
        $team,
        'user@example.com',
        EmailProvider::AmazonSes,
        $bouncedAt,
        'General',
    );
    $healthRecorder->recordDelivered(
        $team,
        'USER@example.com',
        EmailProvider::AmazonSes,
        $bouncedAt->copy()->addMinute(),
    );

    $health = EmailAddressHealth::query()->sole();
    $otherTeam = Team::factory()->create();

    expect($health->email)->toBe('user@example.com')
        ->and($health->status)->toBe(EmailAddressHealthStatus::Suppressed)
        ->and($health->reason)->toBe(EmailAddressHealthReason::PermanentBounce)
        ->and($health->soft_bounce_count)->toBe(1)
        ->and($health->hard_bounce_count)->toBe(1)
        ->and($health->last_delivered_at)->not->toBeNull()
        ->and($healthRecorder->isSuppressed($team, ' User@Example.com '))->toBeTrue()
        ->and($healthRecorder->isSuppressed($otherTeam, 'user@example.com'))->toBeFalse();
});

test('three temporary bounces in a row suppress the address', function () {
    $team = Team::factory()->create();
    $healthRecorder = app(RecordEmailAddressHealth::class);

    foreach ([3, 2] as $daysAgo) {
        $healthRecorder->recordTransientBounce($team, 'user@example.com', EmailProvider::AmazonSes, now()->subDays($daysAgo), 'MailboxFull');
    }

    expect($healthRecorder->isSuppressed($team, 'user@example.com'))->toBeFalse();

    $health = $healthRecorder->recordTransientBounce($team, 'user@example.com', EmailProvider::AmazonSes, now()->subDay(), 'MailboxFull');

    expect($health->status)->toBe(EmailAddressHealthStatus::Suppressed)
        ->and($health->reason)->toBe(EmailAddressHealthReason::RepeatedTransientBounce)
        ->and($health->recent_soft_bounces)->toHaveCount(3)
        ->and($health->suppressed_at)->not->toBeNull();
});

test('a delivery between temporary bounces restarts the count', function () {
    $team = Team::factory()->create();
    $healthRecorder = app(RecordEmailAddressHealth::class);

    $healthRecorder->recordTransientBounce($team, 'user@example.com', EmailProvider::AmazonSes, now()->subDays(5));
    $healthRecorder->recordTransientBounce($team, 'user@example.com', EmailProvider::AmazonSes, now()->subDays(4));
    $healthRecorder->recordDelivered($team, 'user@example.com', EmailProvider::AmazonSes, now()->subDays(3));
    $healthRecorder->recordTransientBounce($team, 'user@example.com', EmailProvider::AmazonSes, now()->subDays(2));
    $health = $healthRecorder->recordTransientBounce($team, 'user@example.com', EmailProvider::AmazonSes, now()->subDay());

    expect($health->status)->toBe(EmailAddressHealthStatus::Risky)
        ->and($health->recent_soft_bounces)->toHaveCount(2)
        ->and($health->soft_bounce_count)->toBe(4);
});

test('a late delivery report only drops the bounces that came before it', function () {
    $team = Team::factory()->create();
    $healthRecorder = app(RecordEmailAddressHealth::class);

    $healthRecorder->recordTransientBounce($team, 'user@example.com', EmailProvider::AmazonSes, now()->subDays(4));
    $healthRecorder->recordTransientBounce($team, 'user@example.com', EmailProvider::AmazonSes, now()->subDays(2));
    // Delivered between the two bounces, but reported after the second one.
    $healthRecorder->recordDelivered($team, 'user@example.com', EmailProvider::AmazonSes, now()->subDays(3));
    $health = $healthRecorder->recordTransientBounce($team, 'user@example.com', EmailProvider::AmazonSes, now()->subDay());

    expect($health->status)->toBe(EmailAddressHealthStatus::Risky)
        ->and($health->recent_soft_bounces)->toHaveCount(2);
});

test('bounces about the message rather than the mailbox do not count toward suppression', function () {
    $team = Team::factory()->create();
    $healthRecorder = app(RecordEmailAddressHealth::class);

    foreach (['MessageTooLarge', 'ContentRejected', 'AttachmentRejected'] as $index => $subtype) {
        $health = $healthRecorder->recordTransientBounce($team, 'user@example.com', EmailProvider::AmazonSes, now()->subDays(3 - $index), $subtype);
    }

    expect($health->status)->toBe(EmailAddressHealthStatus::Risky)
        ->and($health->recent_soft_bounces)->toBeNull()
        ->and($health->soft_bounce_count)->toBe(3);
});

test('repeated temporary bounces stop marketing mail but not transactional mail, whose delivery lifts them', function () {
    $team = Team::factory()->create();
    $healthRecorder = app(RecordEmailAddressHealth::class);

    foreach ([3, 2, 1] as $daysAgo) {
        $healthRecorder->recordTransientBounce($team, 'user@example.com', EmailProvider::AmazonSes, now()->subDays($daysAgo), 'MailboxFull');
    }

    expect($healthRecorder->isSuppressed($team, 'user@example.com'))->toBeTrue()
        ->and($healthRecorder->isSuppressed($team, 'user@example.com', transactional: true))->toBeFalse();

    $health = $healthRecorder->recordDelivered($team, 'user@example.com', EmailProvider::AmazonSes, now());

    expect($health->status)->toBe(EmailAddressHealthStatus::Deliverable)
        ->and($health->suppressed_at)->toBeNull()
        ->and($healthRecorder->isSuppressed($team, 'user@example.com'))->toBeFalse();
});

test('a permanent bounce still blocks transactional mail', function () {
    $team = Team::factory()->create();
    $healthRecorder = app(RecordEmailAddressHealth::class);

    $healthRecorder->recordPermanentBounce($team, 'user@example.com', EmailProvider::AmazonSes, now());

    expect($healthRecorder->isSuppressed($team, 'user@example.com', transactional: true))->toBeTrue();
});

test('send failures are recorded without suppressing the address', function () {
    $team = Team::factory()->create();

    $health = app(RecordEmailAddressHealth::class)->recordFailure(
        $team,
        'user@example.com',
        EmailProvider::Smtp,
        'Delivery failed.',
    );

    expect($health->status)->toBe(EmailAddressHealthStatus::Unknown)
        ->and($health->reason)->toBe(EmailAddressHealthReason::SendFailure)
        ->and($health->failure_count)->toBe(1)
        ->and(app(RecordEmailAddressHealth::class)->isSuppressed($team, 'user@example.com'))->toBeFalse();
});

test('transactional api rejects a workspace suppressed recipient before queueing', function () {
    Queue::fake();
    $email = TransactionalEmail::factory()->published()->create();
    TeamEmailIntegration::factory()->for($email->team)->ses()->create();
    $sender = TeamSender::factory()->for($email->team)->create();
    $email->team->forceFill([
        'active_sender_id' => $sender->id,
        'email_from_address' => $sender->email,
    ])->save();
    EmailAddressHealth::factory()->for($email->team)->suppressed()->create([
        'email' => 'blocked@example.com',
        'reason' => EmailAddressHealthReason::Complaint,
    ]);
    $issued = TeamApiKey::issue($email->team, 'Production');

    $this->withToken($issued['token'])
        ->postJson(route('api.v1.transactional-emails.send', $email->slug), [
            'to' => 'BLOCKED@example.com',
        ])
        ->assertUnprocessable()
        ->assertJsonPath('message', 'This email address is suppressed after it bounced or reported a complaint.')
        ->assertJsonPath('code', 'address_suppressed');

    expect(TransactionalEmailDelivery::query()->count())->toBe(0);
    Queue::assertNotPushed(SendTransactionalEmailDelivery::class);
});

test('a transactional worker rejects an address suppressed after it was queued', function () {
    Mail::fake();
    $email = TransactionalEmail::factory()->published()->create();
    $delivery = TransactionalEmailDelivery::query()->create([
        'team_id' => $email->team_id,
        'transactional_email_id' => $email->id,
        'to_address' => 'late-bounce@example.com',
        'subject' => 'Queued before bounce',
        'html' => '<p>Queued</p>',
        'from_name' => 'Maildun',
        'from_address' => 'mail@example.com',
        'provider' => EmailProvider::AmazonSes,
    ]);
    EmailAddressHealth::factory()->for($email->team)->suppressed()->create([
        'email' => $delivery->to_address,
        'reason' => EmailAddressHealthReason::PermanentBounce,
    ]);

    (new SendTransactionalEmailDelivery($delivery->id))->handle(app(TeamMailer::class));

    expect($delivery->fresh()->status)->toBe(EmailDeliveryStatus::Rejected)
        ->and($delivery->fresh()->failure_reason)
        ->toBe('This email address is suppressed after it bounced or reported a complaint.');
    Mail::assertNothingSent();
});

test('a campaign worker rejects an address suppressed after its recipient batch was prepared', function () {
    Mail::fake();
    $email = Email::factory()->create();
    $delivery = EmailDelivery::factory()->for($email)->create([
        'email_address' => 'late-complaint@example.com',
        'status' => EmailDeliveryStatus::Queued,
    ]);
    EmailAddressHealth::factory()->for($email->team)->suppressed()->create([
        'email' => $delivery->email_address,
        'reason' => EmailAddressHealthReason::Complaint,
    ]);

    (new SendEmailDelivery($delivery->id))->handle(
        app(BuildTrackedEmailHtml::class),
        app(TeamMailer::class),
    );

    expect($delivery->fresh()->status)->toBe(EmailDeliveryStatus::Rejected)
        ->and($delivery->fresh()->failure_reason)
        ->toBe('This email address is suppressed after it bounced or reported a complaint.')
        ->and($delivery->fresh()->failure_code)->toBe(EmailFailureCode::AddressSuppressed)
        ->and($delivery->attempts()->count())->toBe(0);
    Mail::assertNothingSent();
});
