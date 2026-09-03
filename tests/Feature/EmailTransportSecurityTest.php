<?php

use App\Actions\Automations\AdvanceAutomationRun;
use App\Contracts\DnsResolver;
use App\Enums\AutomationAction;
use App\Enums\AutomationRunStatus;
use App\Enums\EmailDeliveryStatus;
use App\Enums\EmailProvider;
use App\Exceptions\EmailTransportException;
use App\Jobs\SendEmailDelivery;
use App\Jobs\SendTransactionalEmailDelivery;
use App\Mail\TeamEmailIntegrationTest;
use App\Models\Audience;
use App\Models\Automation;
use App\Models\EmailDelivery;
use App\Models\EmailDeliveryAttempt;
use App\Models\Subscriber;
use App\Models\Team;
use App\Models\TeamEmailIntegration;
use App\Models\TransactionalEmail;
use App\Models\TransactionalEmailDelivery;
use App\Models\User;
use App\Services\TeamMailer;
use Illuminate\Mail\Mailer;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mailer\Transport\Smtp\Stream\SocketStream;

function rejectTeamEmailTransport(string $message): void
{
    $runtimeMailer = Mockery::mock(Mailer::class);
    $runtimeMailer->shouldReceive('to')->once()->andReturnSelf();
    $runtimeMailer->shouldReceive('send')->once()->andThrow(new TransportException($message));

    Mail::shouldReceive('purge')->with(TeamMailer::MAILER_NAME)->twice();
    Mail::shouldReceive('mailer')->with(TeamMailer::MAILER_NAME)->once()->andReturn($runtimeMailer);
}

test('email provider secrets are never flashed after validation fails', function () {
    $user = User::factory()->create();
    $secrets = [
        'smtp_password' => 'smtp-old-input-secret',
        'ses_secret_access_key' => 'ses-old-input-secret',
        'sendgrid_api_key' => 'sendgrid-old-input-secret',
        'mailgun_password' => 'mailgun-old-input-secret',
        'resend_api_key' => 'resend-old-input-secret',
        'postmark_token' => 'postmark-old-input-secret',
    ];

    $this->actingAs($user)
        ->post(route('teams.email-provider.store', $user->currentTeam), [
            'name' => 'Primary SMTP',
            'provider' => 'smtp',
            'smtp_host' => 'invalid-host',
            'smtp_port' => 587,
            'smtp_username' => 'mailer',
            'smtp_encryption' => 'tls',
            ...$secrets,
        ])
        ->assertInvalid('smtp_host');

    $oldInput = session()->get('_old_input', []);

    expect(array_intersect_key($oldInput, $secrets))->toBe([]);

    foreach ($secrets as $secret) {
        expect(serialize(session()->all()))->not->toContain($secret);
    }
});

test('ses transport failures are replaced with a safe exception without a previous exception', function () {
    $secret = 'sessecretthatmustneverescapeXXXXXXXXXXXX';
    $team = Team::factory()->create();
    $integration = TeamEmailIntegration::factory()->for($team)->ses()->create([
        'settings' => [
            'region' => 'us-east-1',
            'access_key_id' => 'AKIAIOSFODNN7EXAMPLE',
            'secret_access_key' => $secret,
            'configuration_set' => 'maildun-feedback',
            'sns_topic_arn' => 'arn:aws:sns:us-east-1:123456789012:maildun-feedback',
        ],
    ]);

    rejectTeamEmailTransport('Request to AWS SES V2 API failed. Reason: signature for '.$secret.' is invalid.');

    $exception = null;

    try {
        app(TeamMailer::class)->send(
            $team,
            'recipient@example.com',
            new TeamEmailIntegrationTest($team, $integration->provider),
            $team->resolvedEmailFromAddress(),
        );
    } catch (EmailTransportException $caught) {
        $exception = $caught;
    }

    if (! $exception instanceof EmailTransportException) {
        $this->fail('The unsafe SMTP transport exception was not replaced.');
    }

    expect($exception->getMessage())->toBe(__(EmailTransportException::MESSAGE))
        ->not->toContain($secret)
        ->and($exception->getPrevious())->toBeNull()
        ->and(config('mail.mailers.'.TeamMailer::MAILER_NAME))->toBeNull();
});

test('custom smtp refuses non-public dns answers before opening a mail transport', function () {
    Mail::fake();

    $resolver = Mockery::mock(DnsResolver::class);
    $resolver->shouldReceive('resolve')
        ->once()
        ->with('smtp.internal.example')
        ->andReturn(['8.8.8.8', '169.254.169.254']);
    $this->app->instance(DnsResolver::class, $resolver);

    $team = Team::factory()->create();
    $integration = TeamEmailIntegration::factory()->for($team)->smtp()->create([
        'settings' => [
            'host' => 'smtp.internal.example',
            'port' => 587,
            'username' => 'smtp-user',
            'password' => 'smtp-secret',
            'encryption' => 'tls',
        ],
    ]);

    expect(fn () => app(TeamMailer::class)->send(
        $team,
        'recipient@example.com',
        new TeamEmailIntegrationTest($team, $integration->provider),
        $team->resolvedEmailFromAddress(),
    ))->toThrow(EmailTransportException::class, __(EmailTransportException::MESSAGE));

    Mail::assertNothingSent();
});

test('custom smtp pins the socket to a validated address while preserving the tls hostname', function () {
    $resolver = Mockery::mock(DnsResolver::class);
    $resolver->shouldReceive('resolve')
        ->once()
        ->with('smtp.public.example')
        ->andReturn(['8.8.8.8', '2606:4700:4700::1111']);
    $this->app->instance(DnsResolver::class, $resolver);

    $team = Team::factory()->create();
    $integration = TeamEmailIntegration::factory()->for($team)->smtp()->create([
        'settings' => [
            'host' => 'smtp.public.example',
            'port' => 587,
            'username' => 'smtp-user',
            'password' => 'smtp-secret',
            'encryption' => 'tls',
        ],
    ]);
    $transport = new EsmtpTransport('smtp.public.example', 587, false);
    $runtimeMailer = Mockery::mock(Mailer::class);
    $runtimeMailer->shouldReceive('getSymfonyTransport')->once()->andReturn($transport);
    $runtimeMailer->shouldReceive('to')->once()->with('recipient@example.com')->andReturnSelf();
    $runtimeMailer->shouldReceive('send')->once()->andReturnNull();

    Mail::shouldReceive('purge')->with(TeamMailer::MAILER_NAME)->twice();
    Mail::shouldReceive('mailer')->with(TeamMailer::MAILER_NAME)->once()->andReturn($runtimeMailer);

    app(TeamMailer::class)->send(
        $team,
        'recipient@example.com',
        new TeamEmailIntegrationTest($team, $integration->provider),
        $team->resolvedEmailFromAddress(),
    );

    $stream = $transport->getStream();

    if (! $stream instanceof SocketStream) {
        $this->fail('The SMTP transport did not expose a socket stream.');
    }

    expect($stream->getHost())->toBe('8.8.8.8')
        ->and($stream->getStreamOptions())->toMatchArray([
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
                'peer_name' => 'smtp.public.example',
                'SNI_enabled' => true,
                'SNI_server_name' => 'smtp.public.example',
            ],
        ])
        ->and(config('mail.mailers.'.TeamMailer::MAILER_NAME))->toBeNull();
});

test('a required workspace integration never falls back to the environment mailer', function () {
    Mail::fake();

    $team = Team::factory()->create();

    expect(fn () => app(TeamMailer::class)->send(
        $team,
        'recipient@example.com',
        new TeamEmailIntegrationTest($team, EmailProvider::Smtp),
        $team->resolvedEmailFromAddress(),
    ))->toThrow(EmailTransportException::class, __(EmailTransportException::MESSAGE));

    Mail::assertNothingSent();
});

test('a workspace that opted into ui-managed email never falls back after disconnecting', function () {
    Mail::fake();

    $team = Team::factory()->create();

    expect(fn () => app(TeamMailer::class)->send(
        $team,
        'recipient@example.com',
        new TeamEmailIntegrationTest($team, EmailProvider::Smtp),
        $team->resolvedEmailFromAddress(),
    ))->toThrow(EmailTransportException::class, __(EmailTransportException::MESSAGE));

    Mail::assertNothingSent();
});

test('a connection without authorization for the current sender fails closed', function () {
    Mail::fake();

    $team = Team::factory()->create(['email_from_address' => 'current@example.com']);
    $integration = TeamEmailIntegration::factory()
        ->for($team)
        ->smtp()
        ->unverifiedSender()
        ->create();

    expect(app(TeamMailer::class)->hasIntegration($team))->toBeTrue()
        ->and(fn () => app(TeamMailer::class)->send(
            $team,
            'recipient@example.com',
            new TeamEmailIntegrationTest($team, EmailProvider::Smtp),
            $team->resolvedEmailFromAddress(),
        ))->toThrow(EmailTransportException::class, __('The From address is not authorized for the connected email provider.'));

    Mail::assertNothingSent();
});

test('a legacy active provider is treated as disconnected', function () {
    Mail::fake();

    $team = Team::factory()->create();
    $integration = TeamEmailIntegration::factory()->for($team)->postmark()->create();

    expect(app(TeamMailer::class)->hasIntegration($team))->toBeFalse()
        ->and(fn () => app(TeamMailer::class)->send(
            $team,
            'recipient@example.com',
            new TeamEmailIntegrationTest($team, EmailProvider::Postmark),
            $team->resolvedEmailFromAddress(),
        ))->toThrow(EmailTransportException::class, __(EmailTransportException::MESSAGE));

    Mail::assertNothingSent();
});

test('a legacy environment mailer is not accepted as a supported fallback', function () {
    Mail::fake();
    config()->set('mail.default', 'postmark');

    $team = Team::factory()->create();

    expect(fn () => app(TeamMailer::class)->send(
        $team,
        'recipient@example.com',
        new TeamEmailIntegrationTest($team, EmailProvider::Postmark),
        $team->resolvedEmailFromAddress(),
    ))->toThrow(EmailTransportException::class, __(EmailTransportException::MESSAGE));

    Mail::assertNothingSent();
});

test('campaign transactional and automation failures persist only the safe transport message', function () {
    $safeException = new EmailTransportException;
    $safeMessage = $safeException->getMessage();
    $campaignDelivery = EmailDelivery::factory()->create();
    $campaignAttempt = EmailDeliveryAttempt::factory()->for($campaignDelivery, 'delivery')->create();
    $transactionalEmail = TransactionalEmail::factory()->published()->create();
    $transactionalDelivery = TransactionalEmailDelivery::factory()
        ->for($transactionalEmail, 'transactionalEmail')
        ->create(['team_id' => $transactionalEmail->team_id]);

    (new SendEmailDelivery($campaignDelivery->id))->failed($safeException);
    (new SendTransactionalEmailDelivery($transactionalDelivery->id))->failed($safeException);

    $secret = 'automationsessecretthatmustneverescapeXX';
    $automation = Automation::factory()->active()->create();
    $integration = TeamEmailIntegration::factory()->for($automation->team)->ses()->create([
        'settings' => [
            'region' => 'us-east-1',
            'access_key_id' => 'AKIAIOSFODNN7EXAMPLE',
            'secret_access_key' => $secret,
            'configuration_set' => 'maildun-feedback',
            'sns_topic_arn' => 'arn:aws:sns:us-east-1:123456789012:maildun-feedback',
        ],
    ]);
    $email = TransactionalEmail::factory()->for($automation->team)->create();
    $subscriber = Subscriber::factory()
        ->for(Audience::factory()->for($automation->team))
        ->create();
    $run = $automation->runs()->create([
        'subscriber_id' => $subscriber->id,
        'status' => AutomationRunStatus::Pending,
        'graph' => [
            'nodes' => [
                ['id' => 'trigger', 'type' => 'trigger', 'data' => ['kind' => 'subscribed']],
                ['id' => 'send', 'type' => 'action', 'data' => [
                    'kind' => AutomationAction::SendEmail->value,
                    'transactional_email_uuid' => $email->uuid,
                ]],
            ],
            'edges' => [['source' => 'trigger', 'target' => 'send']],
        ],
        'current_node_id' => 'send',
    ]);

    rejectTeamEmailTransport('Request to AWS SES V2 API failed. Reason: signature for '.$secret.' is invalid.');

    app(AdvanceAutomationRun::class)->handle($run);

    $campaignDelivery->refresh();
    $transactionalDelivery->refresh();
    $run->refresh();

    expect($campaignDelivery->status)->toBe(EmailDeliveryStatus::Failed)
        ->and($campaignDelivery->failure_reason)->toBe($safeMessage)
        ->and($campaignAttempt->fresh()->status)->toBe(EmailDeliveryStatus::Failed)
        ->and($campaignAttempt->fresh()->failure_reason)->toBe($safeMessage)
        ->and($transactionalDelivery->status)->toBe(EmailDeliveryStatus::Failed)
        ->and($transactionalDelivery->failure_reason)->toBe($safeMessage)
        ->and($run->status)->toBe(AutomationRunStatus::Failed)
        ->and($run->failure_reason)->toBe($safeMessage)
        ->and($run->steps()->where('node_id', 'send')->value('result'))->toBe(['error' => $safeMessage])
        ->and(serialize([
            $campaignDelivery->failure_reason,
            $transactionalDelivery->failure_reason,
            $run->failure_reason,
            $run->steps()->where('node_id', 'send')->value('result'),
        ]))->not->toContain($secret);
});
