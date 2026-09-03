<?php

namespace App\Enums;

enum EmailTrackingInsightDimension: string
{
    case Classification = 'classification';
    case Country = 'country';
    case Region = 'region';
    case City = 'city';
    case Network = 'network';
    case Client = 'client';
    case Device = 'device';
}
