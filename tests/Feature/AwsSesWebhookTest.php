<?php

use App\Actions\Emails\ProcessSesEvent;
use App\Enums\AutomationTrigger;
use App\Enums\EmailDeliveryStatus;
use App\Enums\EmailProvider;
use App\Enums\SubscriberStatus;
use App\Events\SubscriberLifecycleOccurred;
use App\Models\EmailDelivery;
use App\Models\EmailDeliveryAttempt;
use App\Models\EmailProviderEvent;
use App\Models\Subscriber;
use App\Models\TeamEmailIntegration;
use Aws\Sns\Message;
use Aws\Sns\MessageValidator;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;

function sesFeedbackTopic(string $name = 'maildun-test'): string
{
    return 'arn:aws:sns:us-east-1:123456789012:'.$name;
}

/**
 * @param  array<string, mixed>  $attributes
 */
function createSesFeedbackAttempt(
    EmailDelivery $delivery,
    string $topicArn,
    array $attributes = [],
): EmailDeliveryAttempt {
    return EmailDeliveryAttempt::factory()
        ->for($delivery, 'delivery')
        ->ses()
        ->create([
            'status' => EmailDeliveryStatus::Sent,
            'provider_message_id' => 'ses-message-'.$delivery->id,
            'sent_at' => now(),
            'ses_sns_topic_arn_hash' => hash('sha256', trim($topicArn)),
            ...$attributes,
        ]);
}

/**
 * @param  array<string, mixed>  $payload
 * @return array{certificate: string, certificate_url: string, envelope: array<string, string>}
 */
function signedSnsNotification(string $topicArn, string $messageId, array $payload): array
{
    $privateKey = openssl_pkey_new([
        'private_key_bits' => 2048,
        'private_key_type' => OPENSSL_KEYTYPE_RSA,
    ]);

    if ($privateKey === false) {
        throw new RuntimeException('Unable to create an SNS test signing key.');
    }

    $keyDetails = openssl_pkey_get_details($privateKey);

    if (! is_array($keyDetails) || ! is_string($keyDetails['key'] ?? null)) {
        throw new RuntimeException('Unable to read the SNS test public key.');
    }

    $certificateUrl = 'https://sns.us-east-1.amazonaws.com/SimpleNotificationService-test.pem';
    $envelope = [
        'Type' => 'Notification',
        'MessageId' => $messageId,
        'TopicArn' => $topicArn,
        'Message' => json_encode($payload, JSON_THROW_ON_ERROR),
        'Timestamp' => now()->toISOString(),
        'SignatureVersion' => '2',
        'Signature' => '',
        'SigningCertURL' => $certificateUrl,
    ];
    $message = new Message($envelope);
    $content = (new MessageValidator)->getStringToSign($message);
    $signed = openssl_sign($content, $signature, $privateKey, OPENSSL_ALGO_SHA256);

    if (! $signed) {
        throw new RuntimeException('Unable to sign the SNS test message.');
    }

    $envelope['Signature'] = base64_encode($signature);

    return [
        'certificate' => $keyDetails['key'],
        'certificate_url' => $certificateUrl,
        'envelope' => $envelope,
    ];
}

beforeEach(function () {
    config()->set('services.ses.sns_topic_arn', sesFeedbackTopic());
});

test('the webhook rejects a signature-valid message from an unregistered topic', function () {
    $signedMessage = signedSnsNotification(
        sesFeedbackTopic('unregistered'),
        'sns-unregistered-topic',
        ['eventType' => 'Delivery', 'mail' => ['timestamp' => now()->toISOString()]],
    );
    Http::fake([
        $signedMessage['certificate_url'] => Http::response($signedMessage['certificate']),
    ]);

    $this->postJson(route('webhooks.aws.ses'), $signedMessage['envelope'])
        ->assertForbidden();

    expect(EmailProviderEvent::query()->count())->toBe(0);
    Http::assertNothingSent();
});

test('the webhook rejects malformed SNS envelopes without a server error', function () {
    $this->call(
        method: 'POST',
        uri: route('webhooks.aws.ses'),
        server: ['CONTENT_TYPE' => 'application/json'],
        content: 'not-json',
    )->assertBadRequest();

    expect(EmailProviderEvent::query()->count())->toBe(0);
});

test('the webhook accepts a signature-valid topic registered to an SES integration', function () {
    $topicArn = sesFeedbackTopic('workspace');
    $integration = TeamEmailIntegration::factory()->ses()->create([
        'settings' => [
            'region' => 'us-east-1',
            'access_key_id' => 'AKIAIOSFODNN7EXAMPLE',
            'secret_access_key' => 'sespasswordXXXXXXXXXXXXXXXXXXXXXXXXXXXXX',
            'configuration_set' => 'workspace-events',
            'sns_topic_arn' => $topicArn,
        ],
    ]);
    $signedMessage = signedSnsNotification(
        $topicArn,
        'sns-workspace-topic',
        ['eventType' => 'Open', 'mail' => ['timestamp' => now()->toISOString()]],
    );
    Http::fake([
        $signedMessage['certificate_url'] => Http::response($signedMessage['certificate']),
    ]);

    $this->postJson(route('webhooks.aws.ses'), $signedMessage['envelope'])
        ->assertNoContent();

    expect($integration->fresh()->ses_sns_topic_arn_hash)->toBe(hash('sha256', $topicArn))
        ->and(EmailProviderEvent::query()->where('event_id', 'sns-workspace-topic')->value('ses_sns_topic_arn_hash'))
        ->toBe(hash('sha256', $topicArn));
});

test('the webhook accepts a topic retained by an immutable SES attempt after disconnect', function () {
    $topicArn = sesFeedbackTopic('disconnected-workspace');
    $delivery = EmailDelivery::factory()->create([
        'provider' => EmailProvider::AmazonSes,
        'status' => EmailDeliveryStatus::Sent,
    ]);
    $attempt = createSesFeedbackAttempt($delivery, $topicArn);
    $signedMessage = signedSnsNotification($topicArn, 'sns-disconnected-workspace', [
        'eventType' => 'Delivery',
        'mail' => [
            'messageId' => $attempt->provider_message_id,
            'timestamp' => now()->toISOString(),
            'tags' => ['attempt_uuid' => [$attempt->uuid]],
        ],
        'delivery' => ['timestamp' => now()->toISOString()],
    ]);
    Http::fake([
        $signedMessage['certificate_url'] => Http::response($signedMessage['certificate']),
    ]);

    $this->postJson(route('webhooks.aws.ses'), $signedMessage['envelope'])
        ->assertNoContent();

    expect($attempt->fresh()->status)->toBe(EmailDeliveryStatus::Delivered)
        ->and($delivery->fresh()->status)->toBe(EmailDeliveryStatus::Delivered);
});

test('an attempt UUID correlates delivery feedback and duplicate SNS messages are idempotent', function () {
    $topicArn = sesFeedbackTopic();
    $delivery = EmailDelivery::factory()->create([
        'provider' => EmailProvider::AmazonSes,
        'status' => EmailDeliveryStatus::Sent,
    ]);
    $attempt = createSesFeedbackAttempt($delivery, $topicArn);
    $payload = [
        'eventType' => 'Delivery',
        'mail' => [
            'messageId' => 'a-different-message-id',
            'timestamp' => now()->subSecond()->toISOString(),
            'tags' => ['attempt_uuid' => [$attempt->uuid]],
        ],
        'delivery' => ['timestamp' => now()->toISOString()],
    ];

    $processor = app(ProcessSesEvent::class);
    $processor->handle('sns-attempt-delivery', $payload, $topicArn);
    $processor->handle('sns-attempt-delivery', $payload, $topicArn);

    $event = EmailProviderEvent::query()->sole();

    expect($attempt->fresh()->status)->toBe(EmailDeliveryStatus::Delivered)
        ->and($attempt->fresh()->delivered_at)->not->toBeNull()
        ->and($delivery->fresh()->status)->toBe(EmailDeliveryStatus::Delivered)
        ->and($event->email_delivery_attempt_id)->toBe($attempt->id)
        ->and($event->email_delivery_id)->toBe($delivery->id)
        ->and($event->ses_sns_topic_arn_hash)->toBe(hash('sha256', $topicArn))
        ->and(EmailProviderEvent::query()->count())->toBe(1);
});

test('message ID fallback is scoped to SES and the incoming topic', function () {
    $topicArn = sesFeedbackTopic('workspace-a');
    $otherTopicArn = sesFeedbackTopic('workspace-b');
    $delivery = EmailDelivery::factory()->create([
        'provider' => EmailProvider::AmazonSes,
        'status' => EmailDeliveryStatus::Sent,
    ]);
    $otherDelivery = EmailDelivery::factory()->create([
        'provider' => EmailProvider::AmazonSes,
        'status' => EmailDeliveryStatus::Sent,
    ]);
    $attempt = createSesFeedbackAttempt($delivery, $topicArn, [
        'provider_message_id' => 'shared-message-id',
    ]);
    createSesFeedbackAttempt($otherDelivery, $otherTopicArn, [
        'provider_message_id' => 'shared-message-id',
    ]);

    app(ProcessSesEvent::class)->handle('sns-message-fallback', [
        'eventType' => 'Delivery',
        'mail' => [
            'messageId' => 'shared-message-id',
            'timestamp' => now()->toISOString(),
            'tags' => [],
        ],
        'delivery' => ['timestamp' => now()->toISOString()],
    ], $topicArn);

    expect($attempt->fresh()->status)->toBe(EmailDeliveryStatus::Delivered)
        ->and($delivery->fresh()->status)->toBe(EmailDeliveryStatus::Delivered)
        ->and($otherDelivery->fresh()->status)->toBe(EmailDeliveryStatus::Sent)
        ->and(EmailProviderEvent::query()->sole()->email_delivery_attempt_id)->toBe($attempt->id);
});

test('an attempt cannot be correlated through another valid SES topic', function () {
    $attemptTopicArn = sesFeedbackTopic('workspace-a');
    $incomingTopicArn = sesFeedbackTopic('workspace-b');
    $delivery = EmailDelivery::factory()->create([
        'provider' => EmailProvider::AmazonSes,
        'status' => EmailDeliveryStatus::Sent,
    ]);
    $attempt = createSesFeedbackAttempt($delivery, $attemptTopicArn, [
        'provider_message_id' => 'workspace-a-message',
    ]);

    app(ProcessSesEvent::class)->handle('sns-cross-topic', [
        'eventType' => 'Complaint',
        'mail' => [
            'messageId' => 'workspace-a-message',
            'timestamp' => now()->toISOString(),
            'tags' => ['attempt_uuid' => [$attempt->uuid]],
        ],
        'complaint' => ['timestamp' => now()->toISOString()],
    ], $incomingTopicArn);

    $event = EmailProviderEvent::query()->sole();

    expect($attempt->fresh()->status)->toBe(EmailDeliveryStatus::Sent)
        ->and($delivery->fresh()->status)->toBe(EmailDeliveryStatus::Sent)
        ->and($event->email_delivery_attempt_id)->toBeNull()
        ->and($event->email_delivery_id)->toBeNull();
});

test('a permanent bounce on an older attempt suppresses the subscriber without mutating the parent delivery', function () {
    $topicArn = sesFeedbackTopic();
    $subscriber = Subscriber::factory()->create();
    $delivery = EmailDelivery::factory()->create([
        'subscriber_id' => $subscriber->id,
        'provider' => EmailProvider::AmazonSes,
        'status' => EmailDeliveryStatus::Sent,
    ]);
    $oldAttempt = createSesFeedbackAttempt($delivery, $topicArn, [
        'provider_message_id' => 'old-attempt-message',
    ]);
    $latestAttempt = createSesFeedbackAttempt($delivery, $topicArn, [
        'provider_message_id' => 'latest-attempt-message',
    ]);
    Event::fake();

    app(ProcessSesEvent::class)->handle('sns-late-old-attempt', [
        'eventType' => 'Bounce',
        'mail' => [
            'messageId' => 'old-attempt-message',
            'timestamp' => now()->toISOString(),
            'tags' => ['attempt_uuid' => [$oldAttempt->uuid]],
        ],
        'bounce' => [
            'timestamp' => now()->toISOString(),
            'bounceType' => 'Permanent',
            'bounceSubType' => 'General',
        ],
    ], $topicArn);

    expect($oldAttempt->fresh()->status)->toBe(EmailDeliveryStatus::Bounced)
        ->and($latestAttempt->fresh()->status)->toBe(EmailDeliveryStatus::Sent)
        ->and($delivery->fresh()->status)->toBe(EmailDeliveryStatus::Sent)
        ->and($subscriber->fresh()->status)->toBe(SubscriberStatus::Unsubscribed);

    Event::assertDispatched(SubscriberLifecycleOccurred::class, fn (SubscriberLifecycleOccurred $event): bool => $event->trigger === AutomationTrigger::Unsubscribed
        && $event->subscriber->is($subscriber));
});

test('a permanent bounce updates the latest attempt and unsubscribes the recipient', function () {
    $topicArn = sesFeedbackTopic();
    $subscriber = Subscriber::factory()->create();
    $delivery = EmailDelivery::factory()->create([
        'subscriber_id' => $subscriber->id,
        'provider' => EmailProvider::AmazonSes,
        'status' => EmailDeliveryStatus::Sent,
    ]);
    $attempt = createSesFeedbackAttempt($delivery, $topicArn);
    Event::fake();

    app(ProcessSesEvent::class)->handle('sns-permanent-bounce', [
        'eventType' => 'Bounce',
        'mail' => [
            'timestamp' => now()->toISOString(),
            'tags' => ['attempt_uuid' => [$attempt->uuid]],
        ],
        'bounce' => [
            'timestamp' => now()->toISOString(),
            'bounceType' => 'Permanent',
            'bounceSubType' => 'General',
        ],
    ], $topicArn);

    expect($attempt->fresh()->status)->toBe(EmailDeliveryStatus::Bounced)
        ->and($attempt->fresh()->failure_reason)->toBe('General')
        ->and($delivery->fresh()->status)->toBe(EmailDeliveryStatus::Bounced)
        ->and($subscriber->fresh()->status)->toBe(SubscriberStatus::Unsubscribed)
        ->and($subscriber->fresh()->unsubscribed_at)->not->toBeNull();

    Event::assertDispatched(SubscriberLifecycleOccurred::class, fn (SubscriberLifecycleOccurred $event): bool => $event->trigger === AutomationTrigger::Unsubscribed
        && $event->subscriber->is($subscriber));
});

test('a transient bounce delays a sent attempt and keeps the recipient subscribed', function () {
    $topicArn = sesFeedbackTopic();
    $subscriber = Subscriber::factory()->create();
    $delivery = EmailDelivery::factory()->create([
        'subscriber_id' => $subscriber->id,
        'provider' => EmailProvider::AmazonSes,
        'status' => EmailDeliveryStatus::Sent,
    ]);
    $attempt = createSesFeedbackAttempt($delivery, $topicArn);

    app(ProcessSesEvent::class)->handle('sns-transient-bounce', [
        'eventType' => 'Bounce',
        'mail' => [
            'timestamp' => now()->toISOString(),
            'tags' => ['attempt_uuid' => [$attempt->uuid]],
        ],
        'bounce' => [
            'timestamp' => now()->toISOString(),
            'bounceType' => 'Transient',
            'bounceSubType' => 'MailboxFull',
        ],
    ], $topicArn);

    expect($attempt->fresh()->status)->toBe(EmailDeliveryStatus::Delayed)
        ->and($attempt->fresh()->failure_reason)->toBe('MailboxFull')
        ->and($delivery->fresh()->status)->toBe(EmailDeliveryStatus::Delayed)
        ->and($delivery->fresh()->isRetryable())->toBeTrue()
        ->and($subscriber->fresh()->status)->toBe(SubscriberStatus::Subscribed);
});

test('a transient bounce cannot regress an already delivered attempt', function () {
    $topicArn = sesFeedbackTopic();
    $deliveredAt = now()->subMinute();
    $delivery = EmailDelivery::factory()->create([
        'provider' => EmailProvider::AmazonSes,
        'status' => EmailDeliveryStatus::Delivered,
        'delivered_at' => $deliveredAt,
    ]);
    $attempt = createSesFeedbackAttempt($delivery, $topicArn, [
        'status' => EmailDeliveryStatus::Delivered,
        'delivered_at' => $deliveredAt,
    ]);

    app(ProcessSesEvent::class)->handle('sns-late-transient-bounce', [
        'eventType' => 'Bounce',
        'mail' => [
            'timestamp' => now()->toISOString(),
            'tags' => ['attempt_uuid' => [$attempt->uuid]],
        ],
        'bounce' => [
            'timestamp' => now()->toISOString(),
            'bounceType' => 'Transient',
            'bounceSubType' => 'MailboxFull',
        ],
    ], $topicArn);

    expect($attempt->fresh()->status)->toBe(EmailDeliveryStatus::Delivered)
        ->and($attempt->fresh()->delayed_at)->toBeNull()
        ->and($delivery->fresh()->status)->toBe(EmailDeliveryStatus::Delivered)
        ->and($delivery->fresh()->delayed_at)->toBeNull();
});

test('a complaint updates the latest attempt and unsubscribes the recipient', function () {
    $topicArn = sesFeedbackTopic();
    $subscriber = Subscriber::factory()->create();
    $delivery = EmailDelivery::factory()->create([
        'subscriber_id' => $subscriber->id,
        'provider' => EmailProvider::AmazonSes,
        'status' => EmailDeliveryStatus::Delivered,
        'delivered_at' => now()->subMinute(),
    ]);
    $attempt = createSesFeedbackAttempt($delivery, $topicArn, [
        'status' => EmailDeliveryStatus::Delivered,
        'delivered_at' => now()->subMinute(),
    ]);
    Event::fake();

    app(ProcessSesEvent::class)->handle('sns-complaint', [
        'eventType' => 'Complaint',
        'mail' => ['tags' => ['attempt_uuid' => [$attempt->uuid]]],
        'complaint' => ['timestamp' => now()->toISOString()],
    ], $topicArn);

    expect($attempt->fresh()->status)->toBe(EmailDeliveryStatus::Complained)
        ->and($attempt->fresh()->complained_at)->not->toBeNull()
        ->and($delivery->fresh()->status)->toBe(EmailDeliveryStatus::Complained)
        ->and($subscriber->fresh()->status)->toBe(SubscriberStatus::Unsubscribed);

    Event::assertDispatched(SubscriberLifecycleOccurred::class, fn (SubscriberLifecycleOccurred $event): bool => $event->trigger === AutomationTrigger::Unsubscribed
        && $event->subscriber->is($subscriber));
});

test('late delivery cannot overwrite a bounce and complaint remains terminal', function () {
    $topicArn = sesFeedbackTopic();
    $subscriber = Subscriber::factory()->create();
    $delivery = EmailDelivery::factory()->create([
        'subscriber_id' => $subscriber->id,
        'provider' => EmailProvider::AmazonSes,
        'status' => EmailDeliveryStatus::Sent,
    ]);
    $attempt = createSesFeedbackAttempt($delivery, $topicArn);
    $processor = app(ProcessSesEvent::class);

    $processor->handle('sns-out-of-order-bounce', [
        'eventType' => 'Bounce',
        'mail' => ['tags' => ['attempt_uuid' => [$attempt->uuid]]],
        'bounce' => [
            'timestamp' => now()->toISOString(),
            'bounceType' => 'Permanent',
            'bounceSubType' => 'General',
        ],
    ], $topicArn);
    $processor->handle('sns-out-of-order-delivery', [
        'eventType' => 'Delivery',
        'mail' => ['tags' => ['attempt_uuid' => [$attempt->uuid]]],
        'delivery' => ['timestamp' => now()->addSecond()->toISOString()],
    ], $topicArn);

    expect($attempt->fresh()->status)->toBe(EmailDeliveryStatus::Bounced)
        ->and($delivery->fresh()->status)->toBe(EmailDeliveryStatus::Bounced);

    $processor->handle('sns-terminal-complaint', [
        'eventType' => 'Complaint',
        'mail' => ['tags' => ['attempt_uuid' => [$attempt->uuid]]],
        'complaint' => ['timestamp' => now()->addSeconds(2)->toISOString()],
    ], $topicArn);
    $processor->handle('sns-after-complaint-bounce', [
        'eventType' => 'Bounce',
        'mail' => ['tags' => ['attempt_uuid' => [$attempt->uuid]]],
        'bounce' => [
            'timestamp' => now()->addSeconds(3)->toISOString(),
            'bounceType' => 'Permanent',
            'bounceSubType' => 'General',
        ],
    ], $topicArn);

    expect($attempt->fresh()->status)->toBe(EmailDeliveryStatus::Complained)
        ->and($delivery->fresh()->status)->toBe(EmailDeliveryStatus::Complained)
        ->and($subscriber->fresh()->status)->toBe(SubscriberStatus::Unsubscribed);
});

test('SES opens and clicks are stored without changing first party metrics', function (string $type) {
    $topicArn = sesFeedbackTopic();
    $delivery = EmailDelivery::factory()->create([
        'provider' => EmailProvider::AmazonSes,
        'status' => EmailDeliveryStatus::Sent,
        'opens_count' => 1,
        'clicks_count' => 2,
    ]);
    $attempt = createSesFeedbackAttempt($delivery, $topicArn);

    app(ProcessSesEvent::class)->handle('sns-'.strtolower($type), [
        'eventType' => $type,
        'mail' => [
            'timestamp' => now()->toISOString(),
            'tags' => ['attempt_uuid' => [$attempt->uuid]],
        ],
    ], $topicArn);

    expect($attempt->fresh()->status)->toBe(EmailDeliveryStatus::Sent)
        ->and($delivery->fresh()->opens_count)->toBe(1)
        ->and($delivery->fresh()->clicks_count)->toBe(2)
        ->and(EmailProviderEvent::query()->where('type', $type)->exists())->toBeTrue();
})->with(['Open', 'Click']);

test('unmatched SES feedback is retained without changing another delivery', function () {
    $delivery = EmailDelivery::factory()->create([
        'provider' => EmailProvider::AmazonSes,
        'status' => EmailDeliveryStatus::Queued,
    ]);

    app(ProcessSesEvent::class)->handle('sns-unmatched', [
        'eventType' => 'Bounce',
        'mail' => ['timestamp' => now()->toISOString(), 'tags' => []],
        'bounce' => ['timestamp' => now()->toISOString(), 'bounceType' => 'Permanent'],
    ], sesFeedbackTopic());

    $event = EmailProviderEvent::query()->sole();

    expect($delivery->fresh()->status)->toBe(EmailDeliveryStatus::Queued)
        ->and($event->email_delivery_id)->toBeNull()
        ->and($event->email_delivery_attempt_id)->toBeNull();
});

test('legacy delivery UUID tags are honored only for the configured platform topic', function () {
    $globalTopicArn = sesFeedbackTopic();
    $delivery = EmailDelivery::factory()->create([
        'provider' => EmailProvider::AmazonSes,
        'status' => EmailDeliveryStatus::Sent,
    ]);
    $otherDelivery = EmailDelivery::factory()->create([
        'provider' => EmailProvider::AmazonSes,
        'status' => EmailDeliveryStatus::Sent,
    ]);
    $smtpDelivery = EmailDelivery::factory()->create([
        'provider' => EmailProvider::Smtp,
        'status' => EmailDeliveryStatus::Sent,
    ]);
    $processor = app(ProcessSesEvent::class);

    $processor->handle('sns-legacy-custom-topic', [
        'eventType' => 'Delivery',
        'mail' => ['tags' => ['delivery_uuid' => [$otherDelivery->uuid]]],
        'delivery' => ['timestamp' => now()->toISOString()],
    ], sesFeedbackTopic('workspace'));
    $processor->handle('sns-legacy-platform-topic', [
        'eventType' => 'Delivery',
        'mail' => ['tags' => ['delivery_uuid' => [$delivery->uuid]]],
        'delivery' => ['timestamp' => now()->toISOString()],
    ], $globalTopicArn);
    $processor->handle('sns-legacy-smtp-delivery', [
        'eventType' => 'Delivery',
        'mail' => ['tags' => ['delivery_uuid' => [$smtpDelivery->uuid]]],
        'delivery' => ['timestamp' => now()->toISOString()],
    ], $globalTopicArn);

    expect($delivery->fresh()->status)->toBe(EmailDeliveryStatus::Delivered)
        ->and($otherDelivery->fresh()->status)->toBe(EmailDeliveryStatus::Sent)
        ->and($smtpDelivery->fresh()->status)->toBe(EmailDeliveryStatus::Sent)
        ->and(EmailProviderEvent::query()->where('event_id', 'sns-legacy-custom-topic')->value('email_delivery_id'))
        ->toBeNull()
        ->and(EmailProviderEvent::query()->where('event_id', 'sns-legacy-platform-topic')->value('email_delivery_id'))
        ->toBe($delivery->id)
        ->and(EmailProviderEvent::query()->where('event_id', 'sns-legacy-smtp-delivery')->value('email_delivery_id'))
        ->toBeNull();
});

test('legacy platform bounce feedback still suppresses after a newer SMTP attempt', function () {
    $topicArn = sesFeedbackTopic();
    $subscriber = Subscriber::factory()->create();
    $delivery = EmailDelivery::factory()->create([
        'subscriber_id' => $subscriber->id,
        'provider' => EmailProvider::Smtp,
        'status' => EmailDeliveryStatus::Sent,
    ]);
    $smtpAttempt = EmailDeliveryAttempt::factory()->for($delivery, 'delivery')->create([
        'provider' => EmailProvider::Smtp,
        'status' => EmailDeliveryStatus::Sent,
        'sent_at' => now(),
        'ses_sns_topic_arn_hash' => null,
    ]);
    Event::fake();

    app(ProcessSesEvent::class)->handle('sns-legacy-bounce-after-switch', [
        'eventType' => 'Bounce',
        'mail' => ['tags' => ['delivery_uuid' => [$delivery->uuid]]],
        'bounce' => [
            'timestamp' => now()->toISOString(),
            'bounceType' => 'Permanent',
            'bounceSubType' => 'General',
        ],
    ], $topicArn);

    expect($delivery->fresh()->status)->toBe(EmailDeliveryStatus::Sent)
        ->and($smtpAttempt->fresh()->status)->toBe(EmailDeliveryStatus::Sent)
        ->and($subscriber->fresh()->status)->toBe(SubscriberStatus::Unsubscribed)
        ->and(EmailProviderEvent::query()->sole()->email_delivery_id)->toBe($delivery->id)
        ->and(EmailProviderEvent::query()->sole()->email_delivery_attempt_id)->toBeNull();

    Event::assertDispatched(SubscriberLifecycleOccurred::class);
});
