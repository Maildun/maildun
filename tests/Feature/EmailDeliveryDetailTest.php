<?php

use App\Enums\EmailDeliveryStatus;
use App\Enums\EmailFailureCode;
use App\Enums\EmailStatus;
use App\Enums\TeamRole;
use App\Models\Email;
use App\Models\EmailDelivery;
use App\Models\EmailDeliveryAttempt;
use App\Models\EmailProviderEvent;
use App\Models\Team;
use App\Models\User;

test('a recipient detail lists the timeline, attempts and feedback without raw payloads', function () {
    $user = User::factory()->create();
    $email = Email::factory()->for($user->currentTeam)->create(['status' => EmailStatus::PartiallyFailed]);
    $delivery = EmailDelivery::factory()->for($email)->create([
        'email_address' => 'reader@example.com',
        'status' => EmailDeliveryStatus::Failed,
        'failure_reason' => 'Email delivery failed.',
        'failure_code' => EmailFailureCode::ProviderRefused,
        'sent_at' => now()->subHour(),
    ]);
    EmailDeliveryAttempt::factory()->for($delivery, 'delivery')->ses()->create([
        'status' => EmailDeliveryStatus::Failed,
        'failure_reason' => 'Email delivery failed.',
    ]);
    EmailProviderEvent::factory()->create([
        'email_delivery_id' => $delivery->id,
        'type' => 'Delivery',
        'payload' => ['mail' => ['destination' => ['someone-else@example.com']]],
    ]);

    $this->actingAs($user)
        ->getJson(route('emails.deliveries.show', [$user->currentTeam, $email, $delivery]))
        ->assertOk()
        ->assertJsonPath('email', 'reader@example.com')
        ->assertJsonPath('failure_code', 'The provider refused the message')
        ->assertJsonPath('timeline.0.label', 'Sent')
        ->assertJsonCount(1, 'attempts')
        ->assertJsonPath('attempts.0.provider', 'Amazon SES')
        ->assertJsonPath('events.0.type', 'Delivery')
        ->assertJsonMissingPath('events.0.payload')
        ->assertDontSee('someone-else@example.com');
});

test('a recipient detail is refused to non-members and not found through another campaign or workspace', function () {
    $user = User::factory()->create();
    $email = Email::factory()->for($user->currentTeam)->create(['status' => EmailStatus::Sent]);
    $otherCampaignDelivery = EmailDelivery::factory()
        ->for(Email::factory()->for($user->currentTeam)->create(['status' => EmailStatus::Sent]))
        ->create();
    $ownDelivery = EmailDelivery::factory()->for($email)->create();
    $outsider = User::factory()->create();

    $this->actingAs($user)
        ->getJson(route('emails.deliveries.show', [$user->currentTeam, $email, $otherCampaignDelivery]))
        ->assertNotFound();

    $this->actingAs($outsider)
        ->getJson(route('emails.deliveries.show', [$user->currentTeam, $email, $ownDelivery]))
        ->assertForbidden();

    $otherTeam = Team::factory()->create();
    $otherTeam->members()->attach($user, ['role' => TeamRole::Owner->value]);

    $this->actingAs($user)
        ->getJson(route('emails.deliveries.show', [$otherTeam, $email, $ownDelivery]))
        ->assertNotFound();
});
