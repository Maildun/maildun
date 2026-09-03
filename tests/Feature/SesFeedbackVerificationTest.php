<?php

use App\Models\TeamEmailIntegration;
use App\Models\User;
use App\Services\SesFeedbackVerifier;
use Aws\Command;
use Aws\Exception\AwsException;
use Aws\MockHandler;
use Aws\Result;
use Aws\SesV2\SesV2Client;
use Illuminate\Support\Facades\Mail;

const FEEDBACK_TOPIC = 'arn:aws:sns:us-east-1:123456789012:maildun-feedback';

/** A verifier whose SES client is driven by a queued AWS mock result. */
function verifierReturning(Result|AwsException $response): SesFeedbackVerifier
{
    $handler = new MockHandler;
    $handler->append($response);

    return new class($handler) extends SesFeedbackVerifier
    {
        public function __construct(private readonly MockHandler $handler) {}

        protected function client(array $settings): SesV2Client
        {
            return new SesV2Client([
                'region' => 'us-east-1',
                'version' => 'latest',
                'credentials' => ['key' => 'AKIAIOSFODNN7EXAMPLE', 'secret' => str_repeat('a', 40)],
                'retries' => 0,
                'handler' => $this->handler,
            ]);
        }
    };
}

function sesConnection(): TeamEmailIntegration
{
    return TeamEmailIntegration::factory()->ses()->create([
        'settings' => [
            'region' => 'us-east-1',
            'access_key_id' => 'AKIAIOSFODNN7EXAMPLE',
            'secret_access_key' => str_repeat('b', 40),
            'configuration_set' => 'maildun-feedback',
            'sns_topic_arn' => FEEDBACK_TOPIC,
        ],
    ]);
}

/** @param list<string> $events */
function snsDestination(array $events, bool $enabled = true, string $topicArn = FEEDBACK_TOPIC): array
{
    return [
        'Name' => 'maildun-sns',
        'Enabled' => $enabled,
        'MatchingEventTypes' => $events,
        'SnsDestination' => ['TopicArn' => $topicArn],
    ];
}

test('a configuration set publishing every required event to the topic verifies', function () {
    $failure = verifierReturning(new Result([
        'EventDestinations' => [snsDestination(['DELIVERY', 'BOUNCE', 'COMPLAINT', 'SEND'])],
    ]))->verify(sesConnection());

    expect($failure)->toBeNull();
});

test('a configuration set with no destination for the connection topic is rejected', function (array $destinations) {
    $failure = verifierReturning(new Result(['EventDestinations' => $destinations]))
        ->verify(sesConnection());

    expect($failure)->toContain('maildun-feedback')
        ->toContain('never arrive');
})->with([
    'no destinations at all' => [[]],
    'destination is disabled' => [[snsDestination(['DELIVERY', 'BOUNCE', 'COMPLAINT'], enabled: false)]],
    'destination publishes to another topic' => [[snsDestination(
        ['DELIVERY', 'BOUNCE', 'COMPLAINT'],
        topicArn: 'arn:aws:sns:us-east-1:123456789012:someone-elses-topic',
    )]],
]);

test('a destination missing bounce or complaint events is rejected by name', function () {
    $failure = verifierReturning(new Result([
        'EventDestinations' => [snsDestination(['DELIVERY'])],
    ]))->verify(sesConnection());

    expect($failure)->toContain('Bounce')
        ->toContain('Complaint')
        ->not->toContain('Delivery');
});

test('aws failures become guidance without leaking the request context', function (string $errorCode, string $expected) {
    $exception = new AwsException(
        'Signature failed for AKIAIOSFODNN7EXAMPLE with secret '.str_repeat('b', 40),
        new Command('GetConfigurationSetEventDestinations'),
        ['code' => $errorCode],
    );

    $failure = verifierReturning($exception)->verify(sesConnection());

    expect($failure)->toContain($expected)
        ->not->toContain(str_repeat('b', 40));
})->with([
    'missing configuration set' => ['NotFoundException', 'does not exist in this AWS region'],
    'insufficient iam permissions' => ['AccessDeniedException', 'ses:GetConfigurationSetEventDestinations'],
    'anything else' => ['ThrottlingException', 'could not be reached'],
]);

test('a connection whose feedback cannot be verified is never authorized for sending', function () {
    Mail::fake();

    $user = User::factory()->create();
    $team = $user->currentTeam;
    $team->update(['email_from_address' => 'mail@example.com', 'email_from_name' => 'Maildun HQ']);

    $integration = TeamEmailIntegration::factory()->for($team)->ses()->create([
        'settings' => [
            'region' => 'us-east-1',
            'access_key_id' => 'AKIAIOSFODNN7EXAMPLE',
            'secret_access_key' => str_repeat('b', 40),
            'configuration_set' => 'maildun-feedback',
            'sns_topic_arn' => FEEDBACK_TOPIC,
        ],
        'connected_at' => now(),
    ]);

    $integration->forceFill([
        'last_tested_at' => null,
        'test_from_address' => null,
    ])->save();

    app()->instance(SesFeedbackVerifier::class, verifierReturning(new Result(['EventDestinations' => []])));

    $this->actingAs($user)
        ->post(route('teams.email-provider.test', [$team, $integration]), [
            'from' => 'mail@example.com',
            'to' => 'owner@example.com',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    // The send itself succeeded, so only the feedback check stands between a
    // silently unreportable connection and activation.
    Mail::assertSentCount(1);

    expect($integration->refresh()->last_tested_at)->toBeNull()
        ->and($integration->test_from_address)->toBeNull()
        ->and($integration->isVerified())->toBeFalse();
});
