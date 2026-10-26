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

    /**
     * The conversion event a series with this goal usually tracks.
     */
    public function suggestedConversionEvent(): string
    {
        return match ($this) {
            self::GenerateLeads => 'lead_created',
            self::BookMeetings => 'meeting_booked',
            self::StartTrials => 'trial_started',
            self::DrivePurchases => 'purchase_completed',
            self::UpgradeCustomers => 'upgrade_completed',
            self::WinBackCustomers => 'customer_reactivated',
        };
    }

    /**
     * Suggested conversion event keys, one per goal. Any other valid key can
     * still be entered as a custom event.
     *
     * @return list<array{value: string, label: string, goal: string}>
     */
    public static function conversionEventOptions(): array
    {
        return array_map(
            fn (self $goal): array => [
                'value' => $goal->suggestedConversionEvent(),
                'label' => ucfirst(str_replace('_', ' ', $goal->suggestedConversionEvent())),
                'goal' => $goal->value,
            ],
            self::cases(),
        );
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
