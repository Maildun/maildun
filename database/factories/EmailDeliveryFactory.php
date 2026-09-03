<?php

namespace Database\Factories;

use App\Enums\EmailDeliveryStatus;
use App\Models\Email;
use App\Models\EmailDelivery;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmailDelivery>
 */
class EmailDeliveryFactory extends Factory
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
            'email_address' => fake()->unique()->safeEmail(),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'status' => EmailDeliveryStatus::Queued,
            'provider' => 'smtp',
            'uses_team_email_integration' => false,
        ];
    }
}
