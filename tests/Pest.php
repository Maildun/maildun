<?php

use App\Contracts\MailDomainLookup;
use App\Enums\TeamPermission;
use App\Exceptions\EmailTransportException;
use App\Models\Team;
use App\Models\User;
use App\Models\WorkspaceRole;
use App\Services\SesFeedbackVerifier;
use Aws\Exception\AwsException;
use Aws\MockHandler;
use Aws\Result;
use Aws\SesV2\SesV2Client;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Transport\SesV2Transport;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Symfony\Component\Mailer\Exception\TransportException;
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

/**
 * Give an existing member a custom workspace role holding only the given permissions.
 *
 * @param  list<TeamPermission>  $permissions
 */
function assignCustomRole(Team $team, User $user, array $permissions): void
{
    $role = $team->workspaceRoles()->create([
        'name' => 'custom-'.Str::lower(Str::random(8)),
        'label' => 'Custom role',
        'guard_name' => 'web',
        'is_system' => false,
    ]);
    $role->syncPermissions(array_map(fn (TeamPermission $permission): string => $permission->value, $permissions));

    $team->memberships()
        ->where('user_id', $user->id)
        ->sole()
        ->update(['role' => $role->name]);
}

/**
 * Continue the test as another browser: forget the resolved user and swap in that browser's session.
 *
 * @param  array<string, mixed>  $session  Data captured earlier with session()->all(); empty for a fresh browser.
 */
function switchSession(array $session = []): void
{
    app('auth')->forgetGuards();

    test()->flushSession()->withSession($session);
}

/**
 * Answer mail-domain DNS lookups without touching the network.
 *
 * @param  array<string, bool|null>  $domains  Domains that do (true) or do not (false) accept mail; others are unknown.
 */
function fakeMailDomains(array $domains = []): void
{
    app()->instance(MailDomainLookup::class, new class($domains) implements MailDomainLookup
    {
        /** @param array<string, bool|null> $domains */
        public function __construct(private array $domains) {}

        public function acceptsMail(string $domain): ?bool
        {
            return $this->domains[$domain] ?? null;
        }
    });
}

/**
 * Send ses-v2 mail through the real SesV2Transport, answered by queued AWS
 * results instead of the network. The handler is returned so a test can see
 * what was sent.
 */
function fakeSesApi(Result|AwsException ...$responses): MockHandler
{
    $handler = new MockHandler($responses);

    Mail::extend('ses-v2', fn (array $config): SesV2Transport => new SesV2Transport(
        new SesV2Client([
            'region' => 'us-east-1',
            'version' => 'latest',
            'credentials' => ['key' => 'AKIAIOSFODNN7EXAMPLE', 'secret' => str_repeat('a', 40)],
            'retries' => 0,
            'handler' => $handler,
        ]),
        $config['options'] ?? [],
    ));

    return $handler;
}

/**
 * An SMTP failure carrying the session transcript Symfony appends, one
 * command or reply per line, as the transport would raise it.
 */
function smtpTransportFailure(string $message, int $replyCode, string ...$transcript): TransportException
{
    $exception = new TransportException($message, $replyCode);
    $exception->appendDebug(implode('', array_map(
        fn (string $line): string => '[2026-11-16T10:00:00.000000+00:00] '.$line."\n",
        $transcript,
    )));

    return $exception;
}

/**
 * The sanitized exception for an SMTP session that timed out waiting for the
 * reply to its end-of-data marker: the server may have queued the message.
 */
function unconfirmedSmtpHandover(): EmailTransportException
{
    return EmailTransportException::fromTransport(smtpTransportFailure(
        'Connection to "smtp.example.com:587" timed out.',
        0,
        '> MAIL FROM:<mail@example.com>', '< 250 OK', '> RCPT TO:<reader@example.com>', '< 250 OK',
        '> DATA', '< 354 Go ahead', '> .',
    ));
}
