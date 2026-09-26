<?php

use App\Actions\Emails\ProcessSesEvent;
use App\Actions\Emails\ResolveCampaignOutcome;
use App\Enums\EmailDeliveryStatus;
use App\Enums\EmailProvider;
use App\Enums\EmailStatus;
use App\Models\Email;
use App\Models\EmailDelivery;
use App\Models\EmailDeliveryAttempt;

test('a finished campaign status follows its deliveries', function (array $statuses, int $recipientCount, EmailStatus $expected) {
    $email = Email::factory()->create(['status' => EmailStatus::Sending, 'recipient_count' => $recipientCount]);

    foreach ($statuses as $status) {
        EmailDelivery::factory()->for($email)->create(['status' => $status]);
    }

    expect(app(ResolveCampaignOutcome::class)->handle($email))->toBe($expected);
})->with([
    'everything sent' => [[EmailDeliveryStatus::Sent, EmailDeliveryStatus::Delivered], 2, EmailStatus::Sent],
    'bounces and complaints are recipient outcomes' => [[EmailDeliveryStatus::Bounced, EmailDeliveryStatus::Complained], 2, EmailStatus::Sent],
    'some failed or were rejected' => [[EmailDeliveryStatus::Sent, EmailDeliveryStatus::Rejected], 2, EmailStatus::PartiallyFailed],
    'every recipient failed' => [[EmailDeliveryStatus::Failed, EmailDeliveryStatus::Rejected], 2, EmailStatus::Failed],
    'recipients were never loaded' => [[EmailDeliveryStatus::Sent], 3, EmailStatus::Failed],
]);

test('a late SES reject turns a sent campaign into partially failed', function () {
    $topicArn = 'arn:aws:sns:us-east-1:123456789012:maildun-test';
    config()->set('services.ses.sns_topic_arn', $topicArn);
    $email = Email::factory()->create(['status' => EmailStatus::Sent, 'recipient_count' => 2, 'sent_at' => now()]);
    EmailDelivery::factory()->for($email)->create(['status' => EmailDeliveryStatus::Delivered]);
    $rejected = EmailDelivery::factory()->for($email)->create([
        'provider' => EmailProvider::AmazonSes,
        'status' => EmailDeliveryStatus::Sent,
    ]);
    $attempt = EmailDeliveryAttempt::factory()->for($rejected, 'delivery')->ses()->create([
        'status' => EmailDeliveryStatus::Sent,
        'sent_at' => now(),
        'ses_sns_topic_arn_hash' => hash('sha256', $topicArn),
    ]);

    app(ProcessSesEvent::class)->handle('sns-late-reject-campaign', [
        'eventType' => 'Reject',
        'mail' => ['tags' => ['attempt_uuid' => [$attempt->uuid]]],
        'reject' => ['reason' => 'Bad content'],
    ], $topicArn);

    expect($email->fresh()->status)->toBe(EmailStatus::PartiallyFailed);
});

test('refreshing leaves an in-flight campaign to be finalized', function () {
    $email = Email::factory()->create(['status' => EmailStatus::Sending, 'recipient_count' => 1]);
    EmailDelivery::factory()->for($email)->create(['status' => EmailDeliveryStatus::Failed]);

    app(ResolveCampaignOutcome::class)->refresh($email);

    expect($email->fresh()->status)->toBe(EmailStatus::Sending);
});
