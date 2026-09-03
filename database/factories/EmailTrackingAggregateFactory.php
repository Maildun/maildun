<?php

namespace Database\Factories;

use App\Models\Email;
use App\Models\EmailTrackingAggregate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmailTrackingAggregate>
 */
class EmailTrackingAggregateFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'email_id' => Email::factory(),
            'total_opens_count' => 0,
            'unique_opens_count' => 0,
            'total_clicks_count' => 0,
            'unique_clicks_count' => 0,
            'revision' => 0,
        ];
    }
}
