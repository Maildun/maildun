<?php

use App\Actions\Emails\BuildSesFeedbackHeartbeat;
use App\Models\EmailDeliveryAttempt;
use App\Models\EmailProviderEvent;
use App\Models\TeamEmailIntegration;

function sesAttemptSentAt(TeamEmailIntegration $integration, DateTimeInterface $sentAt): void
{
    EmailDeliveryAttempt::factory()->ses()->create([
        'team_email_integration_id' => $integration->id,
        'sent_at' => $sentAt,
    ]);
}

function sesFeedbackAt(TeamEmailIntegration $integration, DateTimeInterface $receivedAt): void
{
    EmailProviderEvent::factory()->create([
        'ses_sns_topic_arn_hash' => $integration->ses_sns_topic_arn_hash,
        'created_at' => $receivedAt,
    ]);
}

test('feedback that arrived after the last send is healthy', function () {
    $integration = TeamEmailIntegration::factory()->ses()->create();
    sesAttemptSentAt($integration, now()->subHours(3));
    sesFeedbackAt($integration, now()->subHours(2));

    $heartbeat = app(BuildSesFeedbackHeartbeat::class)->handle($integration);

    expect($heartbeat['stale'])->toBeFalse()
        ->and($heartbeat['last_feedback_at'])->not->toBeNull();
});

test('mail sent over an hour ago with no feedback since is stale', function (bool $hadEarlierFeedback) {
    $integration = TeamEmailIntegration::factory()->ses()->create();

    if ($hadEarlierFeedback) {
        sesFeedbackAt($integration, now()->subDays(2));
    }

    sesAttemptSentAt($integration, now()->subHours(2));

    expect(app(BuildSesFeedbackHeartbeat::class)->handle($integration)['stale'])->toBeTrue();
})->with([
    'never any feedback' => [false],
    'feedback only before the send' => [true],
]);

test('a send inside the grace period is not stale yet', function () {
    $integration = TeamEmailIntegration::factory()->ses()->create();
    sesAttemptSentAt($integration, now()->subMinutes(10));

    expect(app(BuildSesFeedbackHeartbeat::class)->handle($integration)['stale'])->toBeFalse();
});

test('connections that do not report feedback have no heartbeat', function () {
    $integration = TeamEmailIntegration::factory()->smtp()->create();

    expect(app(BuildSesFeedbackHeartbeat::class)->handle($integration))->toBeNull();
});
