<?php

namespace Database\Factories;

use App\Enums\EmailAddressHealthStatus;
use App\Models\EmailAddressHealth;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<EmailAddressHealth>
 */
class EmailAddressHealthFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'team_id' => Team::factory(),
            'email' => fake()->unique()->safeEmail(),
            'status' => EmailAddressHealthStatus::Unknown,
        ];
    }

    public function suppressed(): static
    {
        return $this->state(fn (): array => [
            'status' => EmailAddressHealthStatus::Suppressed,
            'suppressed_at' => now(),
        ]);
    }
}
