<?php

namespace App\Exceptions;

use App\Enums\EmailFailureCode;
use RuntimeException;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

final class EmailTransportException extends RuntimeException
{
    public const string MESSAGE = 'Email delivery failed. Check the workspace email provider settings and try again.';

    public const string UNAUTHORIZED_SENDER_MESSAGE = 'The From address is not authorized for the connected email provider.';

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
     * The message stays generic because transport errors can echo credentials;
     * the failure code says which kind of problem it was.
     */
    public function __construct(
        ?string $message = null,
        public readonly EmailFailureCode $failureCode = EmailFailureCode::ProviderRefused,
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
        $providerMessage = mb_strtolower($exception->getMessage());

        foreach (self::KNOWN_FAILURES as $message => $needles) {
            foreach ($needles as $needle) {
                if (str_contains($providerMessage, $needle)) {
                    return new self(__($message));
                }
            }
        }

        return new self;
    }
}
