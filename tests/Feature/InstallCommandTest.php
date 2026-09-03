<?php

use App\Enums\TeamPermission;
use App\Enums\TeamRole;
use App\Models\EmailTemplate;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Spatie\Permission\Models\Permission;

/**
 * The command edits a real .env, so every test points the application at a
 * throwaway file first. Touching the project's own .env would be destructive.
 */
beforeEach(function () {
    $this->originalRegistration = $_ENV['REGISTRATION_ENABLED'] ?? null;

    $this->envDirectory = sys_get_temp_dir().'/maildun-install-'.bin2hex(random_bytes(6));
    File::ensureDirectoryExists($this->envDirectory);

    $this->app->useEnvironmentPath($this->envDirectory);
    $this->app->loadEnvironmentFrom('.env');

    File::put($this->app->environmentFilePath(), <<<'ENV'
    APP_NAME=Maildun
    APP_KEY=base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=
    FILESYSTEM_DISK=local
    ENV);
});

afterEach(function () {
    if ($this->originalRegistration === null) {
        unset($_ENV['REGISTRATION_ENABLED'], $_SERVER['REGISTRATION_ENABLED']);
        putenv('REGISTRATION_ENABLED');
    } else {
        $_ENV['REGISTRATION_ENABLED'] = $_SERVER['REGISTRATION_ENABLED'] = $this->originalRegistration;
        putenv('REGISTRATION_ENABLED='.$this->originalRegistration);
    }

    File::deleteDirectory($this->envDirectory);
});

function installEnvContents(): string
{
    return (string) File::get(app()->environmentFilePath());
}

test('it seeds the permissions every workspace role is built from', function () {
    $this->artisan('app:install', ['--skip-storage' => true, '--no-interaction' => true])
        ->assertSuccessful()
        ->run();

    expect(Permission::query()->pluck('name')->all())
        ->toEqualCanonicalizing(array_column(TeamPermission::cases(), 'value'));
});

test('it does not seed the email builder starter-kit', function () {
    $this->artisan('app:install', ['--skip-storage' => true, '--no-interaction' => true])
        ->assertSuccessful()
        ->run();

    expect(EmailTemplate::query()->whereNull('team_id')->count())->toBe(4);
});

test('it creates the first administrator with their own personal workspace', function () {
    $this->artisan('app:install', [
        '--admin-name' => 'Ada Lovelace',
        '--admin-email' => 'ada@example.com',
        '--admin-password' => 'correct-horse-battery-staple',
        '--skip-storage' => true,
        '--no-interaction' => true,
    ])->assertSuccessful()->run();

    $user = User::query()->where('email', 'ada@example.com')->sole();
    $team = $user->personalTeam();

    expect($user->name)->toBe('Ada Lovelace')
        ->and($user->email_verified_at)->not->toBeNull()
        ->and($team)->not->toBeNull()
        ->and($team->name)->toBe("Ada Lovelace's Team")
        ->and($user->teamRole($team))->toBe(TeamRole::Owner->value)
        ->and($user->fresh()->current_team_id)->toBe($team->id)
        ->and(DB::table('installations')->whereNotNull('completed_at')->exists())->toBeTrue();
});

test('it is safe to re-run from a deploy script', function () {
    $options = [
        '--admin-name' => 'Ada Lovelace',
        '--admin-email' => 'ada@example.com',
        '--admin-password' => 'correct-horse-battery-staple',
        '--skip-storage' => true,
        '--no-interaction' => true,
    ];

    $this->artisan('app:install', $options)->assertSuccessful()->run();
    $this->artisan('app:install', $options)
        ->expectsOutputToContain('already exists')
        ->assertSuccessful()
        ->run();

    expect(User::query()->where('email', 'ada@example.com')->count())->toBe(1)
        ->and(Permission::query()->count())->toBe(count(TeamPermission::cases()));
});

test('it refuses an administrator whose details do not validate', function () {
    $this->artisan('app:install', [
        '--admin-name' => 'Ada Lovelace',
        '--admin-email' => 'not-an-email',
        '--admin-password' => 'correct-horse-battery-staple',
        '--skip-storage' => true,
        '--no-interaction' => true,
    ])
        ->expectsOutputToContain('could not be created')
        ->assertSuccessful()
        ->run();

    expect(User::query()->count())->toBe(0);
});

test('closing registration writes the env value once', function () {
    $this->artisan('app:install', [
        '--registration' => 'closed',
        '--skip-storage' => true,
        '--no-interaction' => true,
    ])->assertSuccessful()->run();

    expect(installEnvContents())->toContain('REGISTRATION_ENABLED=false')
        ->and(substr_count(installEnvContents(), 'REGISTRATION_ENABLED='))->toBe(1)
        ->and(config('fortify.registration_open'))->toBeFalse();

    $this->artisan('app:install', [
        '--registration' => 'open',
        '--skip-storage' => true,
        '--no-interaction' => true,
    ])->assertSuccessful()->run();

    expect(installEnvContents())->toContain('REGISTRATION_ENABLED=true')
        ->and(substr_count(installEnvContents(), 'REGISTRATION_ENABLED='))->toBe(1);
});

test('it refuses an unknown registration value without touching the env file', function () {
    $before = installEnvContents();

    $this->artisan('app:install', [
        '--registration' => 'maybe',
        '--skip-storage' => true,
        '--no-interaction' => true,
    ])->assertFailed()->run();

    expect(installEnvContents())->toBe($before)
        ->and(User::query()->count())->toBe(0);
});

test('a non-interactive run without admin details prints a secure browser setup link', function () {
    $this->artisan('app:install', ['--skip-storage' => true, '--no-interaction' => true])
        ->expectsOutputToContain('/install?expires=')
        ->assertSuccessful()
        ->run();

    expect(User::query()->count())->toBe(0);
});

test('it asks for the sign-up policy and the administrator when run interactively', function () {
    $this->artisan('app:install', ['--skip-storage' => true])
        ->expectsConfirmation('Let anyone create an account? Invited teammates can always sign up either way.', 'no')
        ->expectsQuestion('Administrator name', 'Ada Lovelace')
        ->expectsQuestion('Administrator email', 'ada@example.com')
        ->expectsQuestion('Administrator password', 'correct-horse-battery-staple')
        ->expectsQuestion('Confirm the password', 'correct-horse-battery-staple')
        ->assertSuccessful()
        ->run();

    expect(installEnvContents())->toContain('REGISTRATION_ENABLED=false')
        ->and(User::query()->where('email', 'ada@example.com')->exists())->toBeTrue();
});

test('it reports the values to set by hand when the platform owns the environment', function () {
    /* Laravel Cloud serves the environment from its dashboard, not from disk. */
    File::delete($this->app->environmentFilePath());
    chmod($this->envDirectory, 0o500);

    try {
        $this->artisan('app:install', [
            '--registration' => 'closed',
            '--skip-storage' => true,
            '--no-interaction' => true,
        ])
            ->expectsOutputToContain('REGISTRATION_ENABLED=false')
            ->assertSuccessful()
            ->run();
    } finally {
        chmod($this->envDirectory, 0o700);
    }

    expect(config('fortify.registration_open'))->toBeFalse()
        ->and(File::exists($this->app->environmentFilePath()))->toBeFalse();
});
