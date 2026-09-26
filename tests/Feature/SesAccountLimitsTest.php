<?php

use App\Models\TeamEmailIntegration;
use App\Models\User;
use App\Services\SesAccountLimits;
use Aws\Command;
use Aws\Exception\AwsException;
use Aws\MockHandler;
use Aws\Result;
use Aws\SesV2\SesV2Client;
use Inertia\Testing\AssertableInertia as Assert;

/** Account limits whose SES client answers from a queued mock result. */
function limitsReturning(Result|AwsException ...$responses): SesAccountLimits
{
    $handler = new MockHandler;

    foreach ($responses as $response) {
        $handler->append($response);
    }

    return new class($handler) extends SesAccountLimits
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

test('the SES quota, rate and sandbox state are read from the account', function () {
    $integration = TeamEmailIntegration::factory()->ses()->create();
    $limits = limitsReturning(new Result([
        'SendQuota' => ['Max24HourSend' => 200.0, 'SentLast24Hours' => 150.0, 'MaxSendRate' => 1.0],
        'ProductionAccessEnabled' => false,
    ]));

    expect($limits->fetch($integration))->toBe([
        'available' => true,
        'max_24_hour_send' => 200,
        'sent_last_24_hours' => 150,
        'remaining' => 50,
        'max_send_rate' => 1.0,
        'sandbox' => true,
    ]);
});

test('missing permission is explained without echoing the AWS error', function () {
    $integration = TeamEmailIntegration::factory()->ses()->create();
    $limits = limitsReturning(new AwsException(
        'User arn:aws:iam::123456789012:user/maildun is not authorized',
        new Command('GetAccount'),
        ['code' => 'AccessDeniedException'],
    ));

    expect($limits->fetch($integration))->toBe([
        'available' => false,
        'reason' => 'Grant ses:GetAccount to these credentials to see the sending quota.',
    ]);
});

test('the limits are cached so pages do not call AWS on every load', function () {
    $integration = TeamEmailIntegration::factory()->ses()->create();
    $limits = limitsReturning(new Result([
        'SendQuota' => ['Max24HourSend' => 50000.0, 'SentLast24Hours' => 0.0, 'MaxSendRate' => 14.0],
        'ProductionAccessEnabled' => true,
    ]));

    $first = $limits->fetch($integration);
    $second = $limits->fetch($integration);

    expect($second)->toBe($first)->and($second['sandbox'])->toBeFalse();
});

test('connections that are not SES have no limits', function () {
    $integration = TeamEmailIntegration::factory()->smtp()->create();

    expect(limitsReturning()->fetch($integration))->toBeNull();
});

test('the SES connection page loads the limits as a deferred prop', function () {
    $user = User::factory()->create();
    $integration = TeamEmailIntegration::factory()->for($user->currentTeam)->ses()->create();
    $fake = Mockery::mock(SesAccountLimits::class);
    $fake->shouldReceive('fetch')->andReturn(['available' => false, 'reason' => 'Grant ses:GetAccount to these credentials to see the sending quota.']);
    app()->instance(SesAccountLimits::class, $fake);

    $this->actingAs($user)
        ->get(route('teams.email-provider.show', [$user->currentTeam, $integration->uuid]))
        ->assertInertia(fn (Assert $page) => $page
            ->missing('sesLimits')
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->where('sesLimits.available', false)));
});
