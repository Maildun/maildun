<?php

use App\Services\InstallationState;
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
