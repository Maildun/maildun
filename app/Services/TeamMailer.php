<?php

namespace App\Services;

use App\Enums\EmailProvider;
use App\Exceptions\EmailTransportException;
use App\Models\Team;
use App\Models\TeamEmailIntegration;
use App\Models\TeamSender;
use Illuminate\Contracts\Mail\Mailable;
use Illuminate\Contracts\Mail\Mailer as MailerContract;
use Illuminate\Mail\Mailer as LaravelMailer;
use Illuminate\Mail\SentMessage;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mailer\Transport\Smtp\Stream\SocketStream;

class TeamMailer
{
    public const string MAILER_NAME = 'team-email-integration';

    /** Named limiter holding outbound sends to the configured rate. */
    public const string RATE_LIMITER = 'email-delivery';

    public function __construct(private readonly PublicSmtpHostGuard $publicSmtpHostGuard) {}

    public function send(
        Team $team,
        string $recipient,
        Mailable $mailable,
        ?string $fromAddress,
    ): ?SentMessage {
        if ($fromAddress === null && $team->exists) {
            $fromAddress = $team->fresh()?->resolvedEmailFromAddress();
        }

        return $this->sendResolved(
            $this->resolve($team),
            $recipient,
            $mailable,
            $fromAddress,
        );
    }

    /**
     * Verification must send from the candidate address before it becomes
     * available to workspace delivery, so this one message intentionally
     * bypasses the registered-sender check on the verified connection.
     */
    public function sendSenderVerification(
        Team $team,
        TeamEmailIntegration $integration,
        TeamSender $sender,
        Mailable $mailable,
    ): ?SentMessage {
        if (! $integration->isVerified() || $integration->team_id !== $team->id) {
            throw new EmailTransportException;
        }

        return $this->sendResolved(
            $this->resolveIntegration($integration),
            $sender->email,
            $mailable,
            $sender->email,
            enforceSenderAuthorization: false,
        );
    }

    public function sendWithIntegration(
        Team $team,
        TeamEmailIntegration $integration,
        string $recipient,
        Mailable $mailable,
        string $fromAddress,
    ): ?SentMessage {
        $scopedIntegration = $team->emailIntegration()
            ->whereKey($integration->getKey())
            ->whereNotNull('connected_at')
            ->first();

        if (! $scopedIntegration instanceof TeamEmailIntegration) {
            throw new EmailTransportException;
        }

        // The delivery test proves the connection independently from workspace
        // senders, so its explicit test address is not subject to sender state.
        return $this->sendResolved(
            $this->resolveIntegration($scopedIntegration),
            $recipient,
            $mailable,
            $fromAddress,
            enforceSenderAuthorization: false,
        );
    }

    public function resolve(Team $team): ResolvedEmailTransport
    {
        if (! $team->exists) {
            throw new EmailTransportException;
        }

        $integration = TeamEmailIntegration::query()
            ->where('team_id', $team->getKey())
            ->whereNotNull('connected_at')
            ->first();

        if (! $integration instanceof TeamEmailIntegration || ! $integration->isVerified()) {
            throw new EmailTransportException;
        }

        return $this->resolveIntegration($integration);
    }

    public function sendResolved(
        ResolvedEmailTransport $transport,
        string $recipient,
        Mailable $mailable,
        ?string $fromAddress,
        bool $enforceSenderAuthorization = true,
    ): ?SentMessage {
        if (! $transport->provider->isSupported()) {
            throw new EmailTransportException;
        }

        // A sender override that outlived its authorization must never reach the
        // transport: SES rejects unverified identities outright, and a relay that
        // accepts it still fails SPF/DKIM alignment.
        if ($enforceSenderAuthorization && ! $transport->allowsSender($fromAddress)) {
            throw EmailTransportException::unauthorizedSender();
        }

        try {
            if (! $transport->usesTeamEmailIntegration()) {
                return Mail::to($recipient)->send($mailable);
            }

            return $this->sendUsingResolvedTransport($transport, $recipient, $mailable);
        } catch (TransportExceptionInterface) {
            throw new EmailTransportException;
        }
    }

    public function provider(Team $team): string
    {
        return $this->resolve($team)->provider->value;
    }

    public function hasIntegration(Team $team): bool
    {
        return $this->integration($team) instanceof TeamEmailIntegration;
    }

    /** @return array<string, mixed>|null */
    public function configuration(Team $team): ?array
    {
        $integration = $this->integration($team);

        return $integration === null ? null : $this->configurationFor($integration);
    }

    private function integration(Team $team): ?TeamEmailIntegration
    {
        if (! $team->exists) {
            return null;
        }

        $integration = TeamEmailIntegration::query()
            ->where('team_id', $team->getKey())
            ->whereNotNull('connected_at')
            ->first();

        return $integration instanceof TeamEmailIntegration && $integration->isVerified()
            ? $integration
            : null;
    }

    private function sendUsingResolvedTransport(
        ResolvedEmailTransport $transport,
        string $recipient,
        Mailable $mailable,
    ): ?SentMessage {
        if ($transport->configuration === null) {
            throw new EmailTransportException;
        }

        $customSmtpTarget = $this->validatedCustomSmtpTarget($transport);
        $configurationKey = 'mail.mailers.'.self::MAILER_NAME;

        Mail::purge(self::MAILER_NAME);
        config()->set($configurationKey, $transport->configuration);

        try {
            $runtimeMailer = Mail::mailer(self::MAILER_NAME);

            $this->pinCustomSmtpTransport($runtimeMailer, $customSmtpTarget);

            return $runtimeMailer->to($recipient)->send($mailable);
        } finally {
            try {
                Mail::purge(self::MAILER_NAME);
            } finally {
                config()->set($configurationKey, null);
            }
        }
    }

    /** @return array{hostname: string, address: string}|null */
    private function validatedCustomSmtpTarget(ResolvedEmailTransport $transport): ?array
    {
        if ($transport->provider !== EmailProvider::Smtp) {
            return null;
        }

        $host = $transport->configuration['host'] ?? null;

        if (! is_string($host) || blank($host)) {
            throw new EmailTransportException;
        }

        $addresses = $this->publicSmtpHostGuard->ensureAllowedSmtpHost($host);

        return [
            'hostname' => $host,
            'address' => $addresses[0],
        ];
    }

    /** @param array{hostname: string, address: string}|null $target */
    private function pinCustomSmtpTransport(MailerContract $mailer, ?array $target): void
    {
        if ($target === null) {
            return;
        }

        if (! $mailer instanceof LaravelMailer) {
            throw new EmailTransportException;
        }

        $transport = $mailer->getSymfonyTransport();

        if (! $transport instanceof EsmtpTransport) {
            throw new EmailTransportException;
        }

        $stream = $transport->getStream();

        if (! $stream instanceof SocketStream) {
            throw new EmailTransportException;
        }

        $address = filter_var($target['address'], FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false
            ? '['.$target['address'].']'
            : $target['address'];
        $streamOptions = $stream->getStreamOptions();
        $sslOptions = $streamOptions['ssl'] ?? [];

        if (! is_array($sslOptions)) {
            $sslOptions = [];
        }

        $sslOptions['verify_peer'] = true;
        $sslOptions['verify_peer_name'] = true;
        $sslOptions['peer_name'] = $target['hostname'];
        $sslOptions['SNI_enabled'] = true;
        $sslOptions['SNI_server_name'] = $target['hostname'];
        $streamOptions['ssl'] = $sslOptions;

        $stream->setHost($address);
        $stream->setStreamOptions($streamOptions);
    }

    /** @return array<string, mixed>|null */
    private function configurationFor(TeamEmailIntegration $integration): ?array
    {
        $settings = $integration->settings;

        return match ($integration->provider) {
            EmailProvider::Smtp => $this->customSmtpConfiguration($settings),
            EmailProvider::AmazonSes => $this->sesConfiguration($settings),
            default => null,
        };
    }

    private function resolveIntegration(TeamEmailIntegration $integration): ResolvedEmailTransport
    {
        if (! $integration->hasCompleteConfiguration()) {
            throw new EmailTransportException;
        }

        $settings = $integration->settings;
        $configurationSet = $this->filledString($settings['configuration_set'] ?? null);
        $topicArnHash = $this->filledString($integration->ses_sns_topic_arn_hash);

        return new ResolvedEmailTransport(
            provider: $integration->provider,
            integrationId: $integration->id,
            integrationUuid: $integration->uuid,
            integrationName: $integration->name,
            configuration: $this->configurationFor($integration),
            sesConfigurationSet: $integration->provider === EmailProvider::AmazonSes ? $configurationSet : null,
            sesSnsTopicArnHash: $integration->provider === EmailProvider::AmazonSes ? $topicArnHash : null,
            authorizedFromAddresses: $integration->verifiedSenderAddresses(),
        );
    }

    private function filledString(mixed $value): ?string
    {
        return is_string($value) && filled(trim($value)) ? trim($value) : null;
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    private function customSmtpConfiguration(array $settings): array
    {
        $encryption = $settings['encryption'] ?? 'tls';
        $usesImplicitTls = $encryption === 'ssl';

        return [
            'transport' => 'smtp',
            'scheme' => $usesImplicitTls ? 'smtps' : 'smtp',
            'host' => (string) ($settings['host'] ?? ''),
            'port' => (int) ($settings['port'] ?? ($usesImplicitTls ? 465 : 587)),
            'username' => $settings['username'] ?? null,
            'password' => $settings['password'] ?? null,
            'timeout' => 10,
            'auto_tls' => $encryption !== 'none',
            'require_tls' => $encryption === 'tls',
        ];
    }

    /**
     * Build the SES API transport for one workspace connection.
     *
     * Every credential key is set explicitly, including a null token. Laravel's
     * createSesV2Transport() merges services.ses underneath this array, so an
     * omitted key would silently fall back to the platform-wide IAM credentials
     * and send from the wrong AWS account.
     *
     * ConfigurationSetName has to travel in options: the X-SES-CONFIGURATION-SET
     * header is read by the SES SMTP endpoint, and SesV2Transport ignores it.
     * Without it SES publishes nothing to SNS and all campaign feedback is lost.
     *
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    private function sesConfiguration(array $settings): array
    {
        $configurationSet = $this->filledString($settings['configuration_set'] ?? null);

        return [
            'transport' => 'ses-v2',
            'key' => $this->filledString($settings['access_key_id'] ?? null),
            'secret' => $this->filledString($settings['secret_access_key'] ?? null),
            'region' => $this->filledString($settings['region'] ?? null) ?? 'us-east-1',
            'token' => null,
            'options' => $configurationSet === null
                ? []
                : ['ConfigurationSetName' => $configurationSet],
        ];
    }
}
