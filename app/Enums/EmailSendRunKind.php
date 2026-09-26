<?php

namespace App\Enums;

enum EmailSendRunKind: string
{
    case Initial = 'initial';
    case Retry = 'retry';
    case Resume = 'resume';
}
