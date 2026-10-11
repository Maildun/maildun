<?php

use App\Services\InstallationState;
use Database\Seeders\InstallSeeder;
use Illuminate\Support\Facades\Storage;
use Laravel\Horizon\Contracts\MasterSupervisorRepository;

beforeEach(function () {
    $this->seed(InstallSeeder::class);
    Storage::fake('local');
    Storage::fake('local_public');
    config()->set([
        'filesystems.default' => 'local',
        'queue.default' => 'redis',
    ]);

    $masterSupervisors = Mockery::mock(MasterSupervisorRepository::class);
    $masterSupervisors->shouldReceive('all')->andReturn([(object) ['status' => 'running']]);
    app()->instance(MasterSupervisorRepository::class, $masterSupervisors);
    app()->forgetInstance(InstallationState::class);
});

test('it passes without interaction on a healthy deployment and cleans up its probe files', function () {
    $this->artisan('app:system-test', ['--no-interaction' => true])
        ->expectsOutputToContain('Every check passed')
        ->assertSuccessful()
        ->run();

    Storage::disk('local')->assertDirectoryEmpty('/');
    Storage::disk('local_public')->assertDirectoryEmpty('/');
});

test('it fails with the reason when a check fails', function () {
    config()->set('queue.default', 'sync');

    $this->artisan('app:system-test', ['--no-interaction' => true])
        ->expectsOutputToContain('Set QUEUE_CONNECTION=redis')
        ->assertFailed()
        ->run();
});

test('skipped checks cannot fail the run', function () {
    config()->set('queue.default', 'sync');

    $this->artisan('app:system-test', [
        '--skip' => ['queue', 'horizon'],
        '--no-interaction' => true,
    ])->assertSuccessful()->run();
});
