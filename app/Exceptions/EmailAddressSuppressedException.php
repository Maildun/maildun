<?php

namespace App\Exceptions;

use RuntimeException;

final class EmailAddressSuppressedException extends RuntimeException
{
    public const string MESSAGE = 'This email address is suppressed after a permanent bounce or complaint.';

    public function __construct()
    {
        parent::__construct(__(self::MESSAGE));
    }
}
