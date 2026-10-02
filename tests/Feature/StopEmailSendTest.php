<?php

use App\Actions\Emails\FinalizeEmailSend;
use App\Actions\Emails\RetryEmailDeliveries;
use App\Actions\Emails\StopEmailSend;
use App\Enums\EmailDeliveryStatus;
use App\Enums\EmailStatus;
use App\Enums\TeamRole;
use App\Jobs\SendEmailDelivery;
use App\Models\Audience;
use App\Models\Email;
use App\Models\EmailDelivery;
use App\Models\Subscriber;
use App\Models\Team;
use App\Models\TeamEmailIntegration;
use App\Models\User;
use App\Services\TeamMailer;
use Illuminate\Support\Facades\Bus;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\mock;

test('stopping cancels unclaimed recipients and leaves claimed ones to finish', function () {
    $user = User::factory()->create();
    $email = Email::factory()->for($user->currentTeam)->create([
        'status' => EmailStatus::Sending,
        'recipient_count' => 3,
        'send_started_at' => now(),
    ]);
    $waiting = EmailDelivery::factory()->for($email)->create(['status' => EmailDeliveryStatus::Queued]);
    $claimed = EmailDelivery::factory()->for($email)->create([
        'status' => EmailDeliveryStatus::Sending,
        'send_attempted_at' => now(),
    ]);
    $sent = EmailDelivery::factory()->for($email)->create(['status' => EmailDeliveryStatus::Sent]);

    $this->actingAs($user)
        ->post(route('emails.stop', [$user->currentTeam, $email]))
        ->assertRedirect()
        ->assertInertiaFlash('toast', [
            'type' => 'success',
            'message' => 'Sending stopped. 1 recipient will not receive this campaign.',
        ]);

    expect($email->fresh()->status)->toBe(EmailStatus::Stopped)
        ->and($waiting->fresh()->status)->toBe(EmailDeliveryStatus::Cancelled)
        ->and($claimed->fresh()->status)->toBe(EmailDeliveryStatus::Sending)
        ->and($sent->fresh()->status)->toBe(EmailDeliveryStatus::Sent);
});

test('a worker that loses the race to a stop never sends', function () {
    $email = Email::factory()->create(['status' => EmailStatus::Stopped, 'recipient_count' => 1]);
    $delivery = EmailDelivery::factory()->for($email)->create(['status' => EmailDeliveryStatus::Queued]);
    mock(TeamMailer::class)->shouldNotReceive('sendResolved', 'resolve');

    app()->call([new SendEmailDelivery($delivery->id), 'handle']);

    expect($delivery->fresh())
        ->status->toBe(EmailDeliveryStatus::Queued)
        ->send_attempted_at->toBeNull();
});

test('finalizing a stopped campaign keeps it stopped and cancels late rows', function () {
    $email = Email::factory()->create(['status' => EmailStatus::Stopped, 'recipient_count' => 5]);
    $late = EmailDelivery::factory()->for($email)->create(['status' => EmailDeliveryStatus::Queued]);
    EmailDelivery::factory()->for($email)->create(['status' => EmailDeliveryStatus::Sent]);

    (new FinalizeEmailSend($email->id))();

    expect($email->fresh()->status)->toBe(EmailStatus::Stopped)
        ->and($late->fresh()->status)->toBe(EmailDeliveryStatus::Cancelled);
});

test('only a campaign that is sending can be stopped', function () {
    $email = Email::factory()->create(['status' => EmailStatus::Sent]);

    expect(fn () => app(StopEmailSend::class)->handle($email))
        ->toThrow(ValidationException::class, 'This campaign is not sending.');
});

test('members who cannot manage campaigns cannot stop one', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);
    assignViewerRole($team, $member);
    $email = Email::factory()->for($team)->create(['status' => EmailStatus::Sending]);

    $this->actingAs($member)
        ->post(route('emails.stop', [$team, $email]))
        ->assertForbidden();

    expect($email->fresh()->status)->toBe(EmailStatus::Sending);
});

test('recipients a stop left unloaded are not offered for queueing', function () {
    Bus::fake();

    $user = User::factory()->create();
    $team = $user->currentTeam;
    TeamEmailIntegration::factory()->for($team)->smtp()->create();
    $audience = Audience::factory()->for($team)->create();
    [$reached] = Subscriber::factory()->for($audience)->count(3)->create()->sortBy('id')->values()->all();
    $email = Email::factory()->for($team)->create([
        'audience_id' => $audience->id,
        'html' => '<p>Hello</p>',
        'status' => EmailStatus::Sending,
        'recipient_count' => 3,
        'send_started_at' => now(),
    ]);
    EmailDelivery::factory()->for($email)->create([
        'subscriber_id' => $reached->id,
        'email_address' => $reached->email,
        'status' => EmailDeliveryStatus::Sent,
    ]);

    $this->actingAs($user)->post(route('emails.stop', [$team, $email]))->assertRedirect();

    $this->actingAs($user)
        ->get(route('emails.show', [$team, $email]))
        ->assertInertia(fn (Assert $page) => $page->where('metrics.unqueued', 0));

    $this->actingAs($user)
        ->post(route('emails.queue-remaining', [$team, $email]))
        ->assertInvalid(['email' => 'This campaign was stopped, so its remaining recipients will not be sent to.']);

    expect($email->fresh()->status)->toBe(EmailStatus::Stopped);
    Bus::assertNothingBatched();
});

test('retrying the failed deliveries of a stopped campaign keeps it stopped', function () {
    Bus::fake();

    $team = Team::factory()->create();
    TeamEmailIntegration::factory()->for($team)->smtp()->create();
    $email = Email::factory()->for($team)->create(['status' => EmailStatus::Stopped, 'recipient_count' => 3]);
    $failed = EmailDelivery::factory()->for($email)->create(['status' => EmailDeliveryStatus::Failed]);
    EmailDelivery::factory()->for($email)->create(['status' => EmailDeliveryStatus::Cancelled]);
    EmailDelivery::factory()->for($email)->create(['status' => EmailDeliveryStatus::Sent]);

    app(RetryEmailDeliveries::class)->handle($email);

    expect($email->fresh()->status)->toBe(EmailStatus::Sending);

    $failed->update(['status' => EmailDeliveryStatus::Sent]);
    (new FinalizeEmailSend($email->id))();

    expect($email->fresh()->status)->toBe(EmailStatus::Stopped);
});
