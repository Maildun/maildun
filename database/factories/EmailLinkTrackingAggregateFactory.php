<?php

namespace Database\Factories;

use App\Models\EmailLink;
use App\Models\EmailLinkTrackingAggregate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmailLinkTrackingAggregate>
 */
class EmailLinkTrackingAggregateFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'email_link_id' => EmailLink::factory(),
            'total_clicks_count' => 0,
            'unique_clicks_count' => 0,
            'revision' => 0,
        ];
    }
}
