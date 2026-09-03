<?php

use App\Models\Team;
use App\Models\User;
use App\Models\WorkspaceRole;
use App\Services\SesFeedbackVerifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

/**
 * Stand in for the SES feedback check so a connection test does not reach AWS.
 *
 * Pass a reason to simulate a configuration set that would never publish to SNS.
 */
function fakeSesFeedbackVerification(?string $failure = null): void
{
    $verifier = Mockery::mock(SesFeedbackVerifier::class);
    $verifier->shouldReceive('verify')->andReturn($failure);

    app()->instance(SesFeedbackVerifier::class, $verifier);
}

function assignViewerRole(Team $team, User $user): void
{
    $role = WorkspaceRole::query()->firstOrCreate(
        [
            'team_id' => $team->id,
            'name' => 'viewer',
        ],
        [
            'label' => 'Viewer',
            'guard_name' => 'web',
        ],
    );

    $team->memberships()
        ->where('user_id', $user->id)
        ->sole()
        ->update(['role' => $role->name]);
}
