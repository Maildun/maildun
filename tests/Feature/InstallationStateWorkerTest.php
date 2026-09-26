<?php

use App\Console\Commands\ResumeEmailDeliveriesCommand;
use App\Enums\EmailDeliveryStatus;
use App\Enums\EmailStatus;
use App\Models\Email;
use App\Models\EmailDelivery;
use App\Services\InstallationState;
use Illuminate\Support\Facades\Cache;
use Laravel\Horizon\Contracts\MasterSupervisorRepository;

test('worker state follows the Horizon supervisors on a Redis queue', function (array $masters, string $state) {
    config()->set('queue.default', 'redis');
    $repository = Mockery::mock(MasterSupervisorRepository::class);
    $repository->shouldReceive('all')->once()->andReturn($masters);
    app()->instance(MasterSupervisorRepository::class, $repository);
    app()->forgetInstance(InstallationState::class);

    expect(app(InstallationState::class)->workerState())->toBe($state);
})->with([
    'no supervisor' => [[], 'stopped'],
    'every supervisor paused' => [[(object) ['status' => 'paused']], 'paused'],
    'a supervisor running' => [[(object) ['status' => 'running'], (object) ['status' => 'paused']], 'running'],
]);

test('worker state is unknown when the queue is not Horizon on Redis', function () {
    config()->set('queue.default', 'database');

    expect(app(InstallationState::class)->workerState())->toBe('unknown');
});

test('the scheduler check follows the last delivery sweep', function (?int $minutesAgo, string $status) {
    if ($minutesAgo !== null) {
        Cache::forever(ResumeEmailDeliveriesCommand::LAST_RUN_CACHE_KEY, now()->subMinutes($minutesAgo)->toIso8601String());
    }

    $check = collect(app(InstallationState::class)->systemChecks())->firstWhere('key', 'scheduler');

    expect($check['status'])->toBe($status);
})->with([
    'never ran' => [null, 'pending'],
    'ran recently' => [3, 'ready'],
    'stopped running' => [40, 'failed'],
]);

test('the campaign sends check reports campaigns that stopped moving', function () {
    config()->set('delivery.recovery.stalled_after_minutes', 15);
    $stuck = Email::factory()->create(['status' => EmailStatus::Sending, 'send_started_at' => now()->subHour()]);
    EmailDelivery::factory()->for($stuck)->create(['status' => EmailDeliveryStatus::Queued, 'updated_at' => now()->subHour()]);
    $moving = Email::factory()->create(['status' => EmailStatus::Sending, 'send_started_at' => now()->subHour()]);
    EmailDelivery::factory()->for($moving)->create(['status' => EmailDeliveryStatus::Sent, 'updated_at' => now()->subMinute()]);

    $check = collect(app(InstallationState::class)->systemChecks())->firstWhere('key', 'campaign-sends');

    expect($check['status'])->toBe('failed')
        ->and($check['description'])->toStartWith('1 campaign has had no delivery finish for 15 minutes.');
});
