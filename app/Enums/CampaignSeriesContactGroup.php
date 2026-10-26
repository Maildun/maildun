<?php

namespace App\Enums;

/**
 * Follow-up groups of people reached by a campaign series. Each person sits in
 * exactly one group; a conversion takes precedence over engagement.
 */
enum CampaignSeriesContactGroup: string
{
    case Converted = 'converted';
    case ClickedNotConverted = 'clicked_not_converted';
    case OpenedNotClicked = 'opened_not_clicked';
    case NotEngaged = 'not_engaged';

    public function label(): string
    {
        return match ($this) {
            self::Converted => 'Converted',
            self::ClickedNotConverted => 'Clicked, not converted',
            self::OpenedNotClicked => 'Opened, did not click',
            self::NotEngaged => 'Not engaged',
        };
    }
}
