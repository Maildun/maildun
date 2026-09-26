<?php

namespace App\Enums;

enum TestSendStatus: string
{
    case Queued = 'queued';
    case Sent = 'sent';
    case Failed = 'failed';
}
