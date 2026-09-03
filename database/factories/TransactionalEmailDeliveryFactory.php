<?php

namespace Database\Factories;

use App\Enums\EmailDeliveryStatus;
use App\Models\TransactionalEmail;
use App\Models\TransactionalEmailDelivery;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TransactionalEmailDelivery>
 */
class TransactionalEmailDeliveryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $email = TransactionalEmail::factory()->published();

        return [
            'team_id' => fn (array $attributes): int => TransactionalEmail::query()
                ->whereKey($attributes['transactional_email_id'])
                ->sole()
                ->team_id,
            'transactional_email_id' => $email,
            'team_api_key_id' => null,
            'to_address' => fake()->safeEmail(),
            'subject' => fake()->sentence(),
            'html' => '<p>'.fake()->paragraph().'</p>',
            'from_name' => fake()->name(),
            'from_address' => fake()->safeEmail(),
            'status' => EmailDeliveryStatus::Queued,
            'provider' => 'array',
            'uses_team_email_integration' => false,
        ];
    }
}
