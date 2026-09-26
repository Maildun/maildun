<?php

use App\Enums\TeamPermission;
use App\Enums\TeamRole;
use App\Models\User;
use App\Services\InstallationState;
use Database\Seeders\InstallSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Horizon\Contracts\MasterSupervisorRepository;
use Spatie\Permission\Models\Permission;

function secureBrowserInstallationUrl(): string
{
    return URL::temporarySignedRoute(
        'install.show',
        now()->addHour(),
        absolute: false,
    );
}

function secureSystemInstallationUrl(): string
{
    return URL::temporarySignedRoute(
        'install.system.show',
        now()->addHour(),
        absolute: false,
    );
}

test('the browser installer requires a signed deployment link', function () {
    $this->get(route('install.show'))->assertForbidden();
    $this->get(route('install.system.show'))->assertForbidden();
});

test('the deployment service generates a signed browser installation url', function () {
    $url = app(InstallationState::class)->browserUrl();

    expect($url)->toStartWith(rtrim((string) config('app.url'), '/').'/install?')
        ->toContain('expires=')
        ->toContain('signature=');
});

test('an expired deployment link is rejected', function () {
    $url = URL::temporarySignedRoute(
        'install.show',
        now()->subMinute(),
        absolute: false,
    );

    $this->get($url)->assertForbidden();
});

test('the signed installer reports deployment readiness', function () {
    $this->seed(InstallSeeder::class);
    $url = secureBrowserInstallationUrl();
    parse_str((string) parse_url($url, PHP_URL_QUERY), $signedQuery);

    $response = $this->get($url);

    $response->assertInertia(fn (Assert $page) => $page
        ->component('auth/install')
        ->where('ready', true)
        ->has('checks', 4)
        ->where('systemTestUrl', fn (string $url): bool => str_starts_with($url, '/install/system?'))
        ->where('signedQuery.expires', (string) $signedQuery['expires'])
        ->where('signedQuery.signature', (string) $signedQuery['signature']),
    );
});

test('the signed system page reports sanitized installation checks', function () {
    $this->seed(InstallSeeder::class);

    $response = $this->get(secureSystemInstallationUrl());

    $response->assertInertia(fn (Assert $page) => $page
        ->component('auth/install-system')
        ->has('checks', 10)
        ->where('checks.6.key', 'storage-configuration')
        ->where('checks.7.key', 'storage-round-trip')
        ->where('checks.7.status', 'pending')
        ->where('checks.8.key', 'scheduler')
        ->where('checks.9.key', 'campaign-sends')
        ->where('installUrl', fn (string $url): bool => str_starts_with($url, '/install?')),
    );
});

test('the system page reports paused Horizon workers as failed', function () {
    $this->seed(InstallSeeder::class);
    config()->set('queue.default', 'redis');
    $masterSupervisors = Mockery::mock(MasterSupervisorRepository::class);
    $masterSupervisors->shouldReceive('all')->once()->andReturn([(object) ['status' => 'paused']]);
    app()->instance(MasterSupervisorRepository::class, $masterSupervisors);
    app()->forgetInstance(InstallationState::class);

    $this->get(secureSystemInstallationUrl())->assertInertia(fn (Assert $page) => $page
        ->component('auth/install-system')
        ->where('checks.5.key', 'horizon')
        ->where('checks.5.status', 'failed'),
    );
});

test('the system test round trips temporary files on both storage roles', function () {
    $this->seed(InstallSeeder::class);
    Storage::fake('local');
    Storage::fake('local_public');
    config()->set([
        'filesystems.default' => 'local',
        'filesystems.disks.public' => config('filesystems.disks.local_public'),
    ]);
    $url = secureSystemInstallationUrl();

    $response = $this->post($url);

    $response->assertRedirect();
    $this->get($url)->assertInertia(fn (Assert $page) => $page
        ->component('auth/install-system')
        ->where('checks.7.key', 'storage-round-trip')
        ->where('checks.7.status', 'ready'),
    );
    Storage::disk('local')->assertDirectoryEmpty('/');
    Storage::disk('local_public')->assertDirectoryEmpty('/');
});

test('the installer disables account creation when baseline data is missing', function () {
    Permission::query()
        ->where('name', TeamPermission::ManageAudience->value)
        ->where('guard_name', 'web')
        ->delete();

    $response = $this->get(secureBrowserInstallationUrl());

    $response->assertInertia(fn (Assert $page) => $page
        ->component('auth/install')
        ->where('ready', false)
        ->where('checks.2.key', 'baseline')
        ->where('checks.2.ready', false),
    );
});

test('the installer rejects account creation when deployment checks fail', function () {
    Permission::query()
        ->where('name', TeamPermission::ManageAudience->value)
        ->where('guard_name', 'web')
        ->delete();

    $response = $this->post(secureBrowserInstallationUrl(), [
        'name' => 'Ada Lovelace',
        'email' => 'ada@example.com',
        'password' => 'correct-horse-battery-staple',
        'password_confirmation' => 'correct-horse-battery-staple',
    ]);

    $response->assertSessionHasErrors('installation');
    expect(User::query()->count())->toBe(0)
        ->and(DB::table('installations')->count())->toBe(0);
});

test('administrator details are validated before installation', function () {
    $this->seed(InstallSeeder::class);

    $response = $this->post(secureBrowserInstallationUrl(), []);

    $response->assertSessionHasErrors([
        'name',
        'email',
        'password',
        'password_confirmation',
    ]);
    expect(User::query()->count())->toBe(0)
        ->and(DB::table('installations')->count())->toBe(0);
});

test('the browser installer creates and signs in the first administrator', function () {
    $this->seed(InstallSeeder::class);

    $response = $this->post(secureBrowserInstallationUrl(), [
        'name' => 'Ada Lovelace',
        'email' => 'ada@example.com',
        'password' => 'correct-horse-battery-staple',
        'password_confirmation' => 'correct-horse-battery-staple',
    ]);

    $user = User::query()->where('email', 'ada@example.com')->sole();
    $team = $user->personalTeam();

    $this->assertAuthenticatedAs($user);
    $response->assertRedirect(route('dashboard', ['current_team' => $team]));
    expect($user->email_verified_at)->not->toBeNull()
        ->and($team)->not->toBeNull()
        ->and($team->name)->toBe("Ada Lovelace's Team")
        ->and($user->teamRole($team))->toBe(TeamRole::Owner->value)
        ->and(DB::table('installations')->whereNotNull('completed_at')->exists())->toBeTrue();
});

test('a completed installation cannot be opened or submitted again', function () {
    $this->seed(InstallSeeder::class);
    $url = secureBrowserInstallationUrl();

    $this->post($url, [
        'name' => 'Ada Lovelace',
        'email' => 'ada@example.com',
        'password' => 'correct-horse-battery-staple',
        'password_confirmation' => 'correct-horse-battery-staple',
    ])->assertRedirect();

    $this->get($url)->assertNotFound();
    $this->post($url, [
        'name' => 'Grace Hopper',
        'email' => 'grace@example.com',
        'password' => 'correct-horse-battery-staple',
        'password_confirmation' => 'correct-horse-battery-staple',
    ])->assertNotFound();
    expect(User::query()->count())->toBe(1);
});

test('the database completion marker keeps the installer closed without users', function () {
    $this->seed(InstallSeeder::class);
    $url = secureBrowserInstallationUrl();

    $this->post($url, [
        'name' => 'Ada Lovelace',
        'email' => 'ada@example.com',
        'password' => 'correct-horse-battery-staple',
        'password_confirmation' => 'correct-horse-battery-staple',
    ])->assertRedirect();
    User::query()->delete();

    $this->get($url)->assertNotFound();
});
