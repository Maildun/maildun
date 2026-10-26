<?php

namespace Database\Factories;

use App\Enums\ConversionAttributionType;
use App\Models\CampaignSeries;
use App\Models\EmailConversionEvent;
use App\Models\EmailDelivery;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<EmailConversionEvent>
 */
class EmailConversionEventFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'external_id' => 'evt_'.Str::lower(Str::random(16)),
            'request_hash' => hash('sha256', Str::random(32)),
            'event_key' => 'meeting_booked',
            'email' => fake()->unique()->safeEmail(),
            'attribution_type' => ConversionAttributionType::Unattributed,
            'occurred_at' => now(),
        ];
    }

    /**
     * Credit the series and campaign of a delivery through its click.
     */
    public function clickedFrom(EmailDelivery $delivery): static
    {
        return $this->state(fn (): array => [
            'team_id' => $delivery->email->team_id,
            'email' => Str::lower($delivery->email_address),
            'subscriber_id' => $delivery->subscriber_id,
            'attribution_type' => ConversionAttributionType::Click,
            'campaign_series_id' => $delivery->email->campaign_series_id,
            'email_id' => $delivery->email_id,
            'email_delivery_id' => $delivery->id,
            'touched_at' => $delivery->last_clicked_at ?? $delivery->first_clicked_at,
        ]);
    }

    /**
     * Credit a series without giving any campaign the win.
     */
    public function assistedBy(CampaignSeries $campaignSeries): static
    {
        return $this->state(fn (): array => [
            'team_id' => $campaignSeries->team_id,
            'attribution_type' => ConversionAttributionType::Assisted,
            'campaign_series_id' => $campaignSeries->id,
        ]);
    }

    /**
     * Attach a monetary value in minor units.
     */
    public function worth(int $valueMinor, string $currency = 'USD'): static
    {
        return $this->state(fn (): array => [
            'value_minor' => $valueMinor,
            'currency' => $currency,
        ]);
    }
}
