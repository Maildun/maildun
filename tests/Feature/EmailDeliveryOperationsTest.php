<?php

use App\Concerns\ThrottlesEmailDelivery;
use App\Console\Commands\ResumeEmailDeliveriesCommand;
use App\Enums\EmailDeliveryStatus;
use App\Enums\EmailSendLane;
use App\Enums\EmailStatus;
use App\Jobs\PrepareEmailSendChunk;
use App\Jobs\ProcessAutomationRun;
use App\Jobs\SendAutomationEmailDelivery;
use App\Jobs\SendCampaignTestEmail;
use App\Jobs\SendEmailDelivery;
use App\Jobs\SendTransactionalEmailDelivery;
use App\Models\Email;
use App\Models\EmailDelivery;
use App\Models\EmailDeliveryAttempt;
use App\Models\TransactionalEmail;
use App\Models\TransactionalEmailDelivery;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Pipeline\Pipeline;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * Runs a send job through its middleware and reports whether it got as far as
 * handle(), which is where it would send.
 */
function passesDeliveryMiddleware(object $job): bool
{
    $reachedHandle = false;

    (new Pipeline(app()))
        ->send($job)
        ->through($job->middleware())
        ->then(function () use (&$reachedHandle): void {
            $reachedHandle = true;
        });

    return $reachedHandle;
}

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
        ->and((new SendAutomationEmailDelivery(1))->timeout)->toBeLessThan(config('horizon.defaults.supervisor-1.timeout'))
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

    expect($job->middleware())->each->not->toBeInstanceOf(RateLimited::class)
        ->and($job->retryUntil())->toBeNull()
        ->and($job->tries)->toBe(3);
});

test('a configured send rate throttles every send job', function (string $jobClass) {
    config()->set('delivery.rate_limit.per_second', 14);
    config()->set('delivery.rate_limit.release_after', 7);

    $throttles = collect((new $jobClass(1))->middleware())->whereInstanceOf(RateLimited::class);

    expect($throttles)->toHaveCount(1)
        ->and($throttles->sole()->releaseAfter)->toBe(7);
})->with([SendEmailDelivery::class, SendTransactionalEmailDelivery::class, SendAutomationEmailDelivery::class]);

test('a campaign delivery that can no longer send is dropped before it takes a send slot', function (string $reason) {
    $this->freezeTime();
    // Splits into one slot a second for bulk mail and one for transactional.
    config()->set('delivery.rate_limit.per_second', 2);
    $sending = Email::factory()->create(['status' => EmailStatus::Sending]);
    $waiting = EmailDelivery::factory()->for($sending)->create(['status' => EmailDeliveryStatus::Queued]);
    $leftoverJob = match ($reason) {
        'its batch was cancelled' => (new SendEmailDelivery(EmailDelivery::factory()->for($sending)->create()->id))
            ->withFakeBatch(cancelledAt: now())[0],
        'its campaign was stopped' => new SendEmailDelivery(EmailDelivery::factory()
            ->for(Email::factory()->create(['status' => EmailStatus::Stopped]))
            ->create(['status' => EmailDeliveryStatus::Cancelled])->id),
        'it was already sent' => new SendEmailDelivery(EmailDelivery::factory()->for($sending)->create([
            'status' => EmailDeliveryStatus::Sent,
            'send_attempted_at' => now(),
        ])->id),
    };

    expect(passesDeliveryMiddleware($leftoverJob))->toBeFalse()
        ->and(passesDeliveryMiddleware(new SendEmailDelivery($waiting->id)))->toBeTrue();
})->with(['its batch was cancelled', 'its campaign was stopped', 'it was already sent']);

test('campaign and automation sends cannot take the send slots reserved for transactional mail', function () {
    $this->freezeTime();
    // Four slots a second for bulk mail, one reserved for transactional.
    config()->set('delivery.rate_limit.per_second', 5);

    $bulkSends = collect(range(1, 5))
        ->filter(fn (int $deliveryId): bool => passesDeliveryMiddleware(new SendAutomationEmailDelivery($deliveryId)))
        ->count();

    expect($bulkSends)->toBe(4)
        ->and(passesDeliveryMiddleware(new SendTransactionalEmailDelivery(1)))->toBeTrue()
        ->and(passesDeliveryMiddleware(new SendTransactionalEmailDelivery(2)))->toBeFalse();
});

test('the automation engine never waits for a send slot', function () {
    config()->set('delivery.rate_limit.per_second', 14);

    // retryUntil is stamped at dispatch, so a delayed branch would expire before it ran.
    expect(class_uses_recursive(ProcessAutomationRun::class))->not->toContain(ThrottlesEmailDelivery::class);
});

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
    expect(RateLimiter::limiter(EmailSendLane::Bulk->limiterName())())->toBeInstanceOf(Limit::class)
        ->and(RateLimiter::limiter(EmailSendLane::Bulk->limiterName())()->maxAttempts)->toBe(PHP_INT_MAX);

    config()->set('delivery.rate_limit.per_second', 14);
    $limit = RateLimiter::limiter(EmailSendLane::Bulk->limiterName())();

    expect($limit->maxAttempts)->toBe(12)
        ->and($limit->decaySeconds)->toBe(1);
});

test('the send rate is split into a reserved transactional share and the rest for bulk mail', function (
    int $perSecond,
    int $transactionalShare,
    int $bulkSlots,
    int $transactionalSlots,
    string $transactionalLimiter,
) {
    config()->set('delivery.rate_limit.per_second', $perSecond);
    config()->set('delivery.rate_limit.transactional_share', $transactionalShare);

    expect(RateLimiter::limiter(EmailSendLane::Bulk->limiterName())()->maxAttempts)->toBe($bulkSlots)
        ->and(RateLimiter::limiter(EmailSendLane::Transactional->limiterName())()->maxAttempts)->toBe($transactionalSlots)
        ->and(EmailSendLane::Transactional->limiterName())->toBe($transactionalLimiter);
})->with([
    'a fifth of the rate, rounded down' => [14, 20, 12, 2, 'email-delivery-transactional'],
    'at least one message a second' => [3, 20, 2, 1, 'email-delivery-transactional'],
    'never the whole rate' => [10, 100, 1, 9, 'email-delivery-transactional'],
    'too small a rate to split' => [1, 20, 1, 1, 'email-delivery'],
    'no share reserved' => [14, 0, 14, 14, 'email-delivery'],
]);

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

test('a stalled delivery is not queued again while a job could still run it', function () {
    Queue::fake();
    $email = Email::factory()->create(['status' => EmailStatus::Sending, 'send_started_at' => now()->subHour()]);
    $waiting = EmailDelivery::factory()->for($email)->create(['status' => EmailDeliveryStatus::Queued]);
    $waiting->forceFill(['updated_at' => now()->subHour()])->saveQuietly();
    SendEmailDelivery::dispatch($waiting->id);

    $this->artisan('emails:resume')->assertSuccessful();

    Queue::assertPushed(SendEmailDelivery::class, 1);
    expect($waiting->fresh()->status)->toBe(EmailDeliveryStatus::Queued);
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

test('a campaign whose batch lost its jobs is finalized only once nothing can still run', function (
    bool $jobStillQueued,
    int $minutesSinceLastClaim,
    EmailStatus $expectedStatus,
    bool $batchCancelled,
) {
    Queue::fake();
    $batch = Bus::batch([new SendEmailDelivery(1)])
        ->onQueue(config('delivery.queues.campaigns'))
        ->dispatch();
    DB::table('job_batches')
        ->where('id', $batch->id)
        ->update(['created_at' => now()->subHour()->getTimestamp()]);

    if (! $jobStillQueued) {
        // A fresh fake has an empty queue: the batch's job was lost, the way a
        // Redis flush loses it, while the batch record stays unfinished.
        Queue::fake();
    }

    $email = Email::factory()->create([
        'status' => EmailStatus::Sending,
        'recipient_count' => 1,
        'send_started_at' => now()->subHour(),
        'batch_id' => $batch->id,
    ]);
    EmailDelivery::factory()->for($email)->create([
        'status' => EmailDeliveryStatus::Sent,
        'send_attempted_at' => now()->subMinutes($minutesSinceLastClaim),
    ]);

    $this->artisan('emails:resume')->assertSuccessful();

    expect($email->fresh()->status)->toBe($expectedStatus);
    expect(Bus::findBatch($batch->id)->cancelled())->toBe($batchCancelled);
})->with([
    'its jobs were lost' => [false, 60, EmailStatus::Sent, true],
    'a job is still waiting for a worker' => [true, 60, EmailStatus::Sending, false],
    'a delivery was claimed moments ago' => [false, 1, EmailStatus::Sending, false],
]);

test('the resume sweep records when it last ran', function () {
    $this->freezeTime();

    $this->artisan('emails:resume')->assertSuccessful();

    expect(Cache::get(ResumeEmailDeliveriesCommand::LAST_RUN_CACHE_KEY))->toBe(now()->toIso8601String());
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

test('a stalled transactional delivery is not queued again while a job could still run it', function () {
    Queue::fake();
    $transactionalEmail = TransactionalEmail::factory()->published()->create();
    $delivery = TransactionalEmailDelivery::factory()
        ->for($transactionalEmail, 'transactionalEmail')
        ->create([
            'team_id' => $transactionalEmail->team_id,
            'status' => EmailDeliveryStatus::Queued,
        ]);
    $delivery->forceFill(['updated_at' => now()->subHour()])->saveQuietly();
    SendTransactionalEmailDelivery::dispatch($delivery->id);

    $this->artisan('emails:resume')->assertSuccessful();

    Queue::assertPushed(SendTransactionalEmailDelivery::class, 1);
});

test('a transactional delivery stuck mid-handoff is failed rather than sent a second time', function (
    int $minutesSinceClaim,
    EmailDeliveryStatus $expectedStatus,
) {
    Queue::fake();
    $transactionalEmail = TransactionalEmail::factory()->published()->create();
    $delivery = TransactionalEmailDelivery::factory()
        ->for($transactionalEmail, 'transactionalEmail')
        ->create([
            'team_id' => $transactionalEmail->team_id,
            'status' => EmailDeliveryStatus::Sending,
            'send_attempted_at' => now()->subMinutes($minutesSinceClaim),
        ]);

    $this->artisan('emails:resume')->assertSuccessful();

    // The transport may already have accepted it, so it must never be re-queued.
    Queue::assertNotPushed(SendTransactionalEmailDelivery::class);

    expect($delivery->fresh()->status)->toBe($expectedStatus);
})->with([
    'past the stall window' => [60, EmailDeliveryStatus::Failed],
    'claimed moments ago' => [1, EmailDeliveryStatus::Sending],
]);

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
