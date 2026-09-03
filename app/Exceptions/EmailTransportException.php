<?php

namespace App\Exceptions;

use RuntimeException;

final class EmailTransportException extends RuntimeException
{
    public const string MESSAGE = 'Email delivery failed. Check the workspace email provider settings and try again.';

    public const string UNAUTHORIZED_SENDER_MESSAGE = 'The From address is not authorized for the connected email provider.';

    public function __construct(?string $message = null)
    {
        parent::__construct($message ?? __(self::MESSAGE));
    }

    public static function unauthorizedSender(): self
    {
        return new self(__(self::UNAUTHORIZED_SENDER_MESSAGE));
    }
}
