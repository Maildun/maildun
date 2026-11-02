<?php

/**
 * Every queued job class in app/Jobs.
 *
 * @return list<class-string>
 */
function queuedJobClasses(): array
{
    return collect(glob(app_path('Jobs/*.php')))
        ->map(fn (string $path): string => 'App\\Jobs\\'.basename($path, '.php'))
        ->values()
        ->all();
}

/**
 * Read a job's declared timeout without running its constructor.
 */
function declaredJobTimeout(string $jobClass): ?int
{
    $reflection = new ReflectionClass($jobClass);

    return $reflection->hasProperty('timeout') ? $reflection->getProperty('timeout')->getDefaultValue() : null;
}

test('every queue the app dispatches to has a horizon supervisor on redis', function () {
    $supervisors = collect(config('horizon.defaults'));

    expect($supervisors->pluck('queue')->flatten()->all())
        ->toContain('default', ...array_values(config('delivery.queues')))
        ->and($supervisors->pluck('connection')->unique()->all())->toBe(['redis']);
});

test('every horizon supervisor times out before redis retries its jobs', function () {
    $retryAfter = config('queue.connections.redis.retry_after');

    foreach (config('horizon.defaults') as $name => $supervisor) {
        expect($supervisor['timeout'])->toBeLessThan($retryAfter, "{$name} timeout");
    }
});

test('every job times out before redis retries it', function () {
    $retryAfter = config('queue.connections.redis.retry_after');

    foreach (queuedJobClasses() as $jobClass) {
        expect(declaredJobTimeout($jobClass))->not->toBeNull("{$jobClass} declares no timeout")
            ->toBeLessThan($retryAfter, "{$jobClass} timeout");
    }
});

test('the horizon allow list is trimmed and case-insensitive', function () {
    $previous = [$_SERVER['HORIZON_ALLOWED_EMAILS'] ?? null, $_ENV['HORIZON_ALLOWED_EMAILS'] ?? null];
    $_SERVER['HORIZON_ALLOWED_EMAILS'] = $_ENV['HORIZON_ALLOWED_EMAILS'] = ' Ops@Example.com ,, admin@example.com';

    try {
        $config = require config_path('horizon.php');
    } finally {
        [$_SERVER['HORIZON_ALLOWED_EMAILS'], $_ENV['HORIZON_ALLOWED_EMAILS']] = $previous;
    }

    expect($config['allowed_emails'])->toBe(['ops@example.com', 'admin@example.com']);
});
