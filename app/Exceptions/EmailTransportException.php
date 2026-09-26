<?php

namespace App\Exceptions;

use App\Enums\EmailFailureCode;
use RuntimeException;

final class EmailTransportException extends RuntimeException
{
    public const string MESSAGE = 'Email delivery failed. Check the workspace email provider settings and try again.';

    public const string UNAUTHORIZED_SENDER_MESSAGE = 'The From address is not authorized for the connected email provider.';

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
}
