<?php

namespace App\Enums;

/**
 * How a conversion event was credited. Only Click gives a campaign credit;
 * Assisted and Explicit credit the series alone.
 */
enum ConversionAttributionType: string
{
    case Click = 'click';
    case Assisted = 'assisted';
    case Explicit = 'explicit';
    case Unattributed = 'unattributed';
}
