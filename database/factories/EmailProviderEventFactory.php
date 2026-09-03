<?php

namespace Database\Factories;

use App\Models\EmailDelivery;
use App\Models\EmailProviderEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmailProviderEvent>
 */
class EmailProviderEventFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'provider' => 'ses',
            'event_id' => fake()->uuid(),
            'email_delivery_id' => EmailDelivery::factory(),
            'type' => 'Delivery',
            'payload' => [],
            'occurred_at' => now(),
            'processed_at' => now(),
        ];
    }
}
