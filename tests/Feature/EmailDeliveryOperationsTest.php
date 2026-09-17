<?php

use App\Enums\EmailDeliveryStatus;
use App\Enums\EmailStatus;
use App\Jobs\PrepareEmailSendChunk;
use App\Jobs\SendCampaignTestEmail;
use App\Jobs\SendEmailDelivery;
use App\Jobs\SendTransactionalEmailDelivery;
use App\Models\Email;
use App\Models\EmailDelivery;
use App\Models\EmailDeliveryAttempt;
use App\Models\TransactionalEmail;
use App\Models\TransactionalEmailDelivery;
use App\Services\TeamMailer;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/*
|--------------------------------------------------------------------------
| Queue isolation
|--------------------------------------------------------------------------
*/

test('campaign and transactional sends run on separate queues so neither starves the other', function () {
    Queue::fake();

    PrepareEmailSendChunk::dispatch(1);
    SendEmailDelivery::dispatch(1);
    SendTransactionalEmailDelivery::dispatch(2);
    SendCampaignTestEmail::dispatch(1, 'reviewer@example.com', 'Subject', '<p>Hi</p>', 'Hi');

    Queue::assertPushedOn('campaigns', PrepareEmailSendChunk::class);
    Queue::assertPushedOn('campaigns', SendEmailDelivery::class);
    Queue::assertPushedOn('transactional', SendTransactionalEmailDelivery::class);
    Queue::assertPushedOn('transactional', SendCampaignTestEmail::class);
});

test('email jobs time out before horizon and redis can retry them', function () {
    expect((new PrepareEmailSendChunk(1))->timeout)->toBeLessThan(config('horizon.defaults.supervisor-campaigns.timeout'))
        ->and((new SendEmailDelivery(1))->timeout)->toBeLessThan(config('horizon.defaults.supervisor-campaigns.timeout'))
        ->and((new SendTransactionalEmailDelivery(1))->timeout)->toBeLessThan(config('horizon.defaults.supervisor-transactional.timeout'))
        ->and(config('horizon.defaults.supervisor-campaigns.timeout'))->toBeLessThan(config('queue.connections.redis.retry_after'));
});

test('horizon monitors every delivery queue and records metrics', function () {
    $scheduledCommands = collect(app(Schedule::class)->events())
        ->pluck('command')
        ->filter(fn (mixed $command): bool => is_string($command));

    expect(config('horizon.waits'))->toHaveKeys([
        'redis:default',
        'redis:campaigns',
        'redis:transactional',
    ])->and(config('horizon.metrics.trim_snapshots.job'))->toBeGreaterThanOrEqual(288)
        ->and($scheduledCommands->contains(
            fn (string $command): bool => str_contains($command, 'horizon:snapshot'),
        ))->toBeTrue();
});

test('the example environment boots horizon on redis with independent worker caps', function () {
    $environment = file_get_contents(base_path('.env.example'));

    expect($environment)->toContain('QUEUE_CONNECTION=redis')
        ->toContain('CACHE_STORE=redis')
        ->toContain('HORIZON_TRANSACTIONAL_MAX_PROCESSES=')
        ->toContain('HORIZON_CAMPAIGN_MAX_PROCESSES=')
        ->toContain('HORIZON_NOTIFICATION_EMAIL=');
});

test('an operator can collapse delivery onto a single queue', function () {
    Queue::fake();
    config()->set('delivery.queues.campaigns', 'default');
    config()->set('delivery.queues.transactional', 'default');

    SendEmailDelivery::dispatch(1);
    SendTransactionalEmailDelivery::dispatch(2);
    SendCampaignTestEmail::dispatch(1, 'reviewer@example.com', 'Subject', '<p>Hi</p>', 'Hi');

    Queue::assertPushedOn('default', SendEmailDelivery::class);
    Queue::assertPushedOn('default', SendTransactionalEmailDelivery::class);
    Queue::assertPushedOn('default', SendCampaignTestEmail::class);
});

/*
|--------------------------------------------------------------------------
| Send-rate throttling
|--------------------------------------------------------------------------
*/

test('no rate limit is applied when the operator has not configured one', function () {
    config()->set('delivery.rate_limit.per_second', 0);

    $job = new SendEmailDelivery(1);

    expect($job->middleware())->toBe([])
        ->and($job->retryUntil())->toBeNull()
        ->and($job->tries)->toBe(3);
});

test('a configured send rate throttles both campaign and transactional sends', function (string $jobClass) {
    config()->set('delivery.rate_limit.per_second', 14);
    config()->set('delivery.rate_limit.release_after', 7);

    $middleware = (new $jobClass(1))->middleware();

    expect($middleware)->toHaveCount(1)
        ->and($middleware[0])->toBeInstanceOf(RateLimited::class)
        ->and($middleware[0]->releaseAfter)->toBe(7);
})->with([SendEmailDelivery::class, SendTransactionalEmailDelivery::class]);

test('a throttled job is bounded by time so waiting for a slot never fails a recipient', function () {
    config()->set('delivery.rate_limit.per_second', 14);

    $job = new SendEmailDelivery(1);

    // maxExceptions still stops it after three genuine transport failures; the
    // attempt budget is what waiting for a slot would otherwise exhaust.
    expect($job->retryUntil())->not->toBeNull()
        ->and($job->maxExceptions)->toBe(3);
});

test('the named limiter reflects the configured rate', function () {
    config()->set('delivery.rate_limit.per_second', 0);
    expect(RateLimiter::limiter(TeamMailer::RATE_LIMITER)())->toBeInstanceOf(Limit::class)
        ->and(RateLimiter::limiter(TeamMailer::RATE_LIMITER)()->maxAttempts)->toBe(PHP_INT_MAX);

    config()->set('delivery.rate_limit.per_second', 14);
    $limit = RateLimiter::limiter(TeamMailer::RATE_LIMITER)();

    expect($limit->maxAttempts)->toBe(14)
        ->and($limit->decaySeconds)->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Stalled delivery recovery
|--------------------------------------------------------------------------
*/

test('a delivery whose queued job was lost goes back on the queue', function () {
    Queue::fake();
    $email = Email::factory()->create(['status' => EmailStatus::Sending, 'send_started_at' => now()->subHour()]);
    $stalled = EmailDelivery::factory()->for($email)->create(['status' => EmailDeliveryStatus::Queued]);
    $stalled->forceFill(['updated_at' => now()->subHour()])->saveQuietly();

    $this->artisan('emails:resume')->assertSuccessful();

    Queue::assertPushed(SendEmailDelivery::class, fn ($job): bool => $job->deliveryId === $stalled->id);
    expect($stalled->fresh()->status)->toBe(EmailDeliveryStatus::Queued);
});

test('a delivery that has only just been queued is left alone', function () {
    Queue::fake();
    $email = Email::factory()->create(['status' => EmailStatus::Sending, 'send_started_at' => now()]);
    EmailDelivery::factory()->for($email)->create(['status' => EmailDeliveryStatus::Queued]);

    $this->artisan('emails:resume')->assertSuccessful();

    Queue::assertNothingPushed();
});

test('a delivery stuck mid-handoff is failed rather than sent a second time', function () {
    Queue::fake();
    $email = Email::factory()->create([
        'status' => EmailStatus::Sending,
        'recipient_count' => 1,
        'send_started_at' => now()->subHour(),
    ]);
    $claimed = EmailDelivery::factory()->for($email)->create([
        'status' => EmailDeliveryStatus::Sending,
        'send_attempted_at' => now()->subHour(),
    ]);
    $attempt = EmailDeliveryAttempt::factory()->for($claimed, 'delivery')->create([
        'status' => EmailDeliveryStatus::Sending,
    ]);

    $this->artisan('emails:resume')->assertSuccessful();

    // The transport may already have accepted it, so it must never be re-queued.
    Queue::assertNotPushed(SendEmailDelivery::class);

    expect($claimed->fresh()->status)->toBe(EmailDeliveryStatus::Failed)
        ->and($claimed->fresh()->failure_reason)->not->toBeNull()
        ->and($attempt->fresh()->status)->toBe(EmailDeliveryStatus::Failed);
});

test('a campaign whose batch vanished mid-send is finalized instead of stranded', function () {
    Queue::fake();
    $email = Email::factory()->create([
        'status' => EmailStatus::Sending,
        'recipient_count' => 2,
        'send_started_at' => now()->subHour(),
        'batch_id' => 'a-batch-that-no-longer-exists',
    ]);
    EmailDelivery::factory()->for($email)->create(['status' => EmailDeliveryStatus::Sent]);
    EmailDelivery::factory()->for($email)->create(['status' => EmailDeliveryStatus::Failed]);

    $this->artisan('emails:resume')->assertSuccessful();

    expect($email->fresh()->status)->toBe(EmailStatus::PartiallyFailed)
        ->and($email->fresh()->sent_at)->not->toBeNull();
});

test('a campaign whose loader batch vanished before snapshotting is failed', function () {
    Queue::fake();
    $email = Email::factory()->create([
        'status' => EmailStatus::Queued,
        'recipient_count' => 10,
        'send_started_at' => now()->subHour(),
        'batch_id' => 'a-loader-batch-that-no-longer-exists',
    ]);

    $this->artisan('emails:resume')->assertSuccessful();

    expect($email->fresh()->status)->toBe(EmailStatus::Failed)
        ->and($email->fresh()->sent_at)->not->toBeNull();
});

test('a campaign whose batch is still running is not finalized early', function () {
    Queue::fake();
    $batch = Bus::batch([new SendEmailDelivery(1)])->dispatch();
    $email = Email::factory()->create([
        'status' => EmailStatus::Sending,
        'recipient_count' => 1,
        'send_started_at' => now()->subHour(),
        'batch_id' => $batch->id,
    ]);
    EmailDelivery::factory()->for($email)->create(['status' => EmailDeliveryStatus::Sent]);

    $this->artisan('emails:resume')->assertSuccessful();

    expect($email->fresh()->status)->toBe(EmailStatus::Sending);
});

test('a stalled transactional delivery goes back on the queue', function () {
    Queue::fake();
    $transactionalEmail = TransactionalEmail::factory()->published()->create();
    $delivery = TransactionalEmailDelivery::factory()
        ->for($transactionalEmail, 'transactionalEmail')
        ->create([
            'team_id' => $transactionalEmail->team_id,
            'status' => EmailDeliveryStatus::Queued,
        ]);
    $delivery->forceFill(['updated_at' => now()->subHour()])->saveQuietly();

    $this->artisan('emails:resume')->assertSuccessful();

    Queue::assertPushed(
        SendTransactionalEmailDelivery::class,
        fn ($job): bool => $job->deliveryId === $delivery->id,
    );
});

/*
|--------------------------------------------------------------------------
| Credential separation
|--------------------------------------------------------------------------
*/

test('the platform SES mailer never borrows S3 object storage credentials', function () {
    $config = file_get_contents(config_path('services.php'));
    $sesBlock = Str::between($config, "'ses' => [", '],');

    expect($sesBlock)->not->toContain('AWS_ACCESS_KEY_ID')
        ->not->toContain('AWS_SECRET_ACCESS_KEY')
        ->not->toContain('AWS_DEFAULT_REGION')
        ->toContain('MAIL_SES_KEY')
        ->toContain('MAIL_SES_SECRET');
});
