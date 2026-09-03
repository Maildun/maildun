<?php

namespace App\Enums;

enum EmailTrackingEventType: string
{
    case Open = 'open';
    case Click = 'click';
}
