<?php

namespace App\Enums;

enum CampaignSeriesGoal: string
{
    case GenerateLeads = 'generate_leads';
    case BookMeetings = 'book_meetings';
    case StartTrials = 'start_trials';
    case DrivePurchases = 'drive_purchases';
    case UpgradeCustomers = 'upgrade_customers';
    case WinBackCustomers = 'win_back_customers';

    public function label(): string
    {
        return match ($this) {
            self::GenerateLeads => 'Generate leads',
            self::BookMeetings => 'Book meetings',
            self::StartTrials => 'Start trials',
            self::DrivePurchases => 'Drive purchases',
            self::UpgradeCustomers => 'Upgrade customers',
            self::WinBackCustomers => 'Win back customers',
        };
    }

    /** @return list<array{value: string, label: string}> */
    public static function options(): array
    {
        return array_map(
            fn (self $goal): array => ['value' => $goal->value, 'label' => $goal->label()],
            self::cases(),
        );
    }
}
