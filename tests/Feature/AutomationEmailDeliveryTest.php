<?php

use App\Actions\Emails\ProcessSesEvent;
use App\Enums\EmailAddressHealthReason;
use App\Enums\EmailAddressHealthStatus;
use App\Enums\EmailDeliveryStatus;
use App\Enums\EmailProvider;
use App\Enums\SubscriberStatus;
use App\Mail\AutomationEmail;
use App\Models\Audience;
use App\Models\Automation;
use App\Models\AutomationEmailDelivery;
use App\Models\AutomationRun;
use App\Models\EmailAddressHealth;
use App\Models\EmailProviderEvent;
use App\Models\Subscriber;
use App\Models\Team;
use App\Models\TransactionalEmail;

test('automation mail carries its feedback correlation tag', function () {
    $team = Team::factory()->create();
    $audience = Audience::factory()->for($team)->create();
    $subscriber = Subscriber::factory()->for($audience)->create();
    $automation = Automation::factory()->for($team)->create();
    $run = AutomationRun::factory()->for($automation)->for($subscriber)->create();
    $email = TransactionalEmail::factory()->for($team)->published()->create();
    $delivery = AutomationEmailDelivery::factory()->for($run, 'run')->create([
        'team_id' => $team->id,
        'transactional_email_id' => $email->id,
        'subscriber_id' => $subscriber->id,
        'to_address' => $subscriber->email,
        'provider' => EmailProvider::AmazonSes,
    ]);

    $mailable = new AutomationEmail($email, $subscriber, 'Subject', '<p>Body</p>', $delivery);

    expect($mailable->envelope()->metadata)->toBe([
        'automation_delivery_uuid' => $delivery->uuid,
    ]);
});

test('an automation complaint is persisted and suppresses the workspace address', function () {
    $topicArn = 'arn:aws:sns:us-east-1:123456789012:automation-feedback';
    $team = Team::factory()->create();
    $audience = Audience::factory()->for($team)->create();
    $subscriber = Subscriber::factory()->for($audience)->create();
    $automation = Automation::factory()->for($team)->create();
    $run = AutomationRun::factory()->for($automation)->for($subscriber)->create();
    $delivery = AutomationEmailDelivery::factory()->for($run, 'run')->create([
        'team_id' => $team->id,
        'subscriber_id' => $subscriber->id,
        'to_address' => $subscriber->email,
        'status' => EmailDeliveryStatus::Sent,
        'provider' => EmailProvider::AmazonSes,
        'provider_message_id' => 'automation-message-id',
        'ses_sns_topic_arn_hash' => hash('sha256', $topicArn),
    ]);

    app(ProcessSesEvent::class)->handle('automation-complaint-event', [
        'eventType' => 'Complaint',
        'mail' => [
            'messageId' => 'automation-message-id',
            'timestamp' => now()->subSecond()->toISOString(),
            'tags' => ['automation_delivery_uuid' => [$delivery->uuid]],
        ],
        'complaint' => ['timestamp' => now()->toISOString()],
    ], $topicArn);

    $health = EmailAddressHealth::query()->sole();
    $event = EmailProviderEvent::query()->sole();

    expect($delivery->fresh()->status)->toBe(EmailDeliveryStatus::Complained)
        ->and($delivery->fresh()->complained_at)->not->toBeNull()
        ->and($subscriber->fresh()->status)->toBe(SubscriberStatus::Unsubscribed)
        ->and($health->status)->toBe(EmailAddressHealthStatus::Suppressed)
        ->and($health->reason)->toBe(EmailAddressHealthReason::Complaint)
        ->and($health->complaint_count)->toBe(1)
        ->and($event->automation_email_delivery_id)->toBe($delivery->id);
});
