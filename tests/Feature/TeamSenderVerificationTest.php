<?php

use App\Jobs\SendTeamSenderVerification;
use App\Mail\SenderVerificationEmail;
use App\Models\TeamEmailIntegration;
use App\Models\TeamSender;
use App\Models\User;
use App\Services\TeamMailer;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;

test('a sender is verified only after its queued verification email is opened', function () {
    Mail::fake();
    Carbon::setTestNow(now());

    $user = User::factory()->create();
    $team = $user->currentTeam;
    $integration = TeamEmailIntegration::factory()->withoutSender()->for($team)->ses()->create();
    $sender = TeamSender::factory()->for($team)->pending()->create([
        'email' => 'news@example.com',
    ]);

    (new SendTeamSenderVerification(
        $sender->id,
        $integration->id,
        $integration->verification_version,
    ))->handle(app(TeamMailer::class));

    $verificationUrl = null;

    Mail::assertSent(SenderVerificationEmail::class, function (SenderVerificationEmail $mail) use (&$verificationUrl): bool {
        $verificationUrl = $mail->verificationUrl;

        return $mail->hasTo('news@example.com') && $mail->hasFrom('news@example.com');
    });

    expect($verificationUrl)->toBeString()
        ->and($sender->refresh()->verification_sent_at)->not->toBeNull()
        ->and($sender->email_verified_at)->toBeNull();

    $this->get($verificationUrl)
        ->assertOk()
        ->assertSee('Sender verified');

    expect($sender->refresh()->email_verified_at)->not->toBeNull()
        ->and($sender->verified_email_integration_id)->toBe($integration->id)
        ->and($sender->verified_email_integration_version)->toBe($integration->verification_version)
        ->and($team->refresh()->active_sender_id)->toBe($sender->id)
        ->and($team->email_from_address)->toBe('news@example.com');

    Carbon::setTestNow();
});

test('adding and resending a sender verification both dispatch queue jobs', function () {
    Queue::fake();

    $user = User::factory()->create();
    $team = $user->currentTeam;
    $integration = TeamEmailIntegration::factory()->withoutSender()->for($team)->ses()->create();

    $this->actingAs($user)
        ->post(route('teams.sender.store', $team), [
            'name' => 'Newsletter',
            'email' => 'news@example.com',
        ])
        ->assertRedirect();

    $sender = $team->senders()->sole();

    Queue::assertPushed(SendTeamSenderVerification::class, fn (SendTeamSenderVerification $job): bool => $job->senderId === $sender->id
        && $job->integrationId === $integration->id
        && $job->verificationVersion === $integration->verification_version);

    $this->actingAs($user)
        ->post(route('teams.sender.verification.store', [$team, $sender]))
        ->assertRedirect();

    Queue::assertPushedTimes(SendTeamSenderVerification::class, 2);
});

test('only a verified sender can become the workspace default', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    TeamEmailIntegration::factory()->withoutSender()->for($team)->ses()->create();
    $pending = TeamSender::factory()->for($team)->pending()->create();
    $verified = TeamSender::factory()->for($team)->create([
        'email' => 'verified@example.com',
        'name' => 'Verified sender',
    ]);

    $this->actingAs($user)
        ->patch(route('teams.sender.default.update', [$team, $pending]))
        ->assertSessionHasErrors('sender');

    $this->actingAs($user)
        ->patch(route('teams.sender.default.update', [$team, $verified]))
        ->assertRedirect();

    expect($team->refresh()->active_sender_id)->toBe($verified->id)
        ->and($team->email_from_address)->toBe('verified@example.com');
});

test('a sender verification link is rejected after the delivery connection changes', function () {
    Mail::fake();

    $user = User::factory()->create();
    $team = $user->currentTeam;
    $integration = TeamEmailIntegration::factory()->withoutSender()->for($team)->ses()->create();
    $sender = TeamSender::factory()->for($team)->pending()->create();
    $job = new SendTeamSenderVerification(
        $sender->id,
        $integration->id,
        $integration->verification_version,
    );
    $job->handle(app(TeamMailer::class));

    $verificationUrl = null;
    Mail::assertSent(SenderVerificationEmail::class, function (SenderVerificationEmail $mail) use (&$verificationUrl): bool {
        $verificationUrl = $mail->verificationUrl;

        return true;
    });

    $integration->increment('verification_version');

    $this->get($verificationUrl)->assertStatus(409);
    expect($sender->refresh()->email_verified_at)->toBeNull();
});
