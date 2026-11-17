<?php

namespace App\Exceptions;

use App\Enums\EmailFailureCode;
use Aws\Exception\AwsException;
use GuzzleHttp\Exception\ConnectException;
use RuntimeException;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

final class EmailTransportException extends RuntimeException
{
    public const string MESSAGE = 'Email delivery failed. Check the workspace email provider settings and try again.';

    public const string UNAUTHORIZED_SENDER_MESSAGE = 'The From address is not authorized for the connected email provider.';

    public const string OUTCOME_UNKNOWN_MESSAGE = 'The email provider took the message but never confirmed it, so it may have been delivered. It is not retried automatically.';

    /**
     * Known provider failures mapped to fixed, credential-free explanations.
     *
     * The raw provider text is only matched against, never kept: SMTP replies
     * can echo usernames or tokens, so it must not reach logs or the database.
     *
     * @var array<string, list<string>>
     */
    private const array KNOWN_FAILURES = [
        'Amazon SES rejected the message because an address is not verified in this region. Verify the From identity, and while the account is in the SES sandbox verify the recipient too.' => [
            'email address is not verified',
            'identities failed the check',
        ],
        'The provider rejected the credentials. Check the access key and secret, or the SMTP username and password.' => [
            'security token included in the request is invalid',
            'signature we calculated does not match',
            'unrecognizedclient',
            'failed to authenticate',
            'authentication failed',
        ],
        'The IAM user is not allowed to send email. Grant it the ses:SendEmail and ses:SendRawEmail permissions.' => [
            'not authorized to perform',
            'accessdenied',
        ],
        'The configuration set does not exist in this Amazon SES region. Check the region and configuration set name.' => [
            'configuration set',
        ],
        'Sending is paused for this Amazon SES account.' => [
            'sending is paused',
        ],
        'The provider is throttling sends. Try again in a moment.' => [
            'maximum sending rate exceeded',
            'throttl',
        ],
        'Could not connect to the email provider. Check the host, port and encryption.' => [
            'connection could not be established',
            'unable to connect',
        ],
    ];

    /**
     * Known failures that sending the same message again cannot fix: they
     * need the account, the credentials or the identity changed first.
     *
     * @var list<string>
     */
    private const array PERMANENT_FAILURES = [
        'Amazon SES rejected the message because an address is not verified in this region. Verify the From identity, and while the account is in the SES sandbox verify the recipient too.',
        'The provider rejected the credentials. Check the access key and secret, or the SMTP username and password.',
        'The IAM user is not allowed to send email. Grant it the ses:SendEmail and ses:SendRawEmail permissions.',
        'The configuration set does not exist in this Amazon SES region. Check the region and configuration set name.',
        'Sending is paused for this Amazon SES account.',
    ];

    /**
     * SES API error codes that a retry gets back unchanged. Throttling and
     * the daily quota are left out: those clear up on their own.
     *
     * @var list<string>
     */
    private const array PERMANENT_SES_ERROR_CODES = [
        'MessageRejected',
        'MailFromDomainNotVerifiedException',
        'AccountSuspendedException',
        'SendingPausedException',
        'NotFoundException',
        'BadRequestException',
        'AccessDeniedException',
        'UnrecognizedClientException',
        'InvalidClientTokenId',
        'InvalidSignatureException',
        'SignatureDoesNotMatch',
    ];

    /**
     * The message stays generic because transport errors can echo credentials;
     * the failure code says which kind of problem it was. A permanent failure
     * fails the send at once instead of spending the queue's retries.
     */
    public function __construct(
        ?string $message = null,
        public readonly EmailFailureCode $failureCode = EmailFailureCode::ProviderRefused,
        public readonly bool $permanent = false,
    ) {
        parent::__construct($message ?? __(self::MESSAGE));
    }

    public static function unauthorizedSender(): self
    {
        return new self(__(self::UNAUTHORIZED_SENDER_MESSAGE), EmailFailureCode::SenderUnauthorized);
    }

    public static function providerUnavailable(): self
    {
        return new self(failureCode: EmailFailureCode::ProviderUnavailable);
    }

    public static function fromTransport(TransportExceptionInterface $exception): self
    {
        if (self::wasHandedOverWithoutReply($exception) || self::wasSentToSesWithoutAnswer($exception)) {
            return new self(__(self::OUTCOME_UNKNOWN_MESSAGE), EmailFailureCode::NoReport);
        }

        $providerMessage = mb_strtolower($exception->getMessage());
        $permanent = self::isPermanentRejection($exception);

        foreach (self::KNOWN_FAILURES as $message => $needles) {
            foreach ($needles as $needle) {
                if (str_contains($providerMessage, $needle)) {
                    return new self(
                        __($message),
                        permanent: $permanent || in_array($message, self::PERMANENT_FAILURES, true),
                    );
                }
            }
        }

        return new self(permanent: $permanent);
    }

    /**
     * Whether the message may have gone out anyway, so sending it again could
     * mail the recipient twice.
     */
    public function outcomeIsUnknown(): bool
    {
        return $this->failureCode === EmailFailureCode::NoReport;
    }

    /**
     * A 5xx SMTP reply is a permanent refusal by definition; the SES API says
     * so through its error code.
     */
    private static function isPermanentRejection(TransportExceptionInterface $exception): bool
    {
        $previous = $exception->getPrevious();

        if ($previous instanceof AwsException) {
            return in_array($previous->getAwsErrorCode(), self::PERMANENT_SES_ERROR_CODES, true);
        }

        $replyCode = (int) $exception->getCode();

        return $replyCode >= 500 && $replyCode < 600;
    }

    /**
     * An SMTP session that already sent the end-of-data marker handed the
     * whole message over. If it then failed without an SMTP reply (a timeout
     * or a dropped connection) the server may well have queued it. A 4xx or
     * 5xx reply is a definite refusal, and anything that failed before the
     * marker never reached the server, so both are safe to retry. The
     * transcript can contain credentials, so it is only matched, never kept.
     */
    private static function wasHandedOverWithoutReply(TransportExceptionInterface $exception): bool
    {
        $replyCode = (int) $exception->getCode();

        if ($replyCode >= 400 && $replyCode < 600) {
            return false;
        }

        return preg_match('/\] > \.$/m', $exception->getDebug()) === 1;
    }

    /**
     * The SES API equivalent: the HTTP connection was up, so the request may
     * have reached SES, but it timed out or dropped before SES answered. SES
     * can accept the message and still miss the read timeout. A failure to
     * connect never reached SES, and an error document is a definite refusal,
     * so both of those stay safe to retry.
     */
    private static function wasSentToSesWithoutAnswer(TransportExceptionInterface $exception): bool
    {
        $previous = $exception->getPrevious();

        if (! $previous instanceof AwsException
            || ! $previous->isConnectionError()
            || $previous->getAwsErrorCode() !== null) {
            return false;
        }

        return ! $previous->getPrevious() instanceof ConnectException;
    }
}
