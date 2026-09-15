<?php

namespace App\Enums;

enum EmailAddressHealthStatus: string
{
    case Unknown = 'unknown';
    case Deliverable = 'deliverable';
    case Risky = 'risky';
    case Suppressed = 'suppressed';
}
