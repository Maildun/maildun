<?php

namespace Database\Factories;

use App\Models\Team;
use App\Models\TeamEmailIntegration;
use App\Models\TeamSender;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TeamSender>
 */
class TeamSenderFactory extends Factory
{
    public function configure(): static
    {
        return $this->afterCreating(function (TeamSender $sender): void {
            if ($sender->email_verified_at === null) {
                return;
            }

            $integration = TeamEmailIntegration::query()
                ->where('team_id', $sender->team_id)
                ->first();

            if (! $integration instanceof TeamEmailIntegration || ! $integration->isVerified()) {
                return;
            }

            $sender->forceFill([
                'verified_email_integration_id' => $integration->id,
                'verified_email_integration_version' => $integration->verification_version,
            ])->save();
        });
    }

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'name' => fake()->company(),
            'email' => fake()->unique()->safeEmail(),
            'reply_to' => null,
            'email_verified_at' => now(),
        ];
    }

    public function pending(): static
    {
        return $this->state(fn (): array => [
            'email_verified_at' => null,
            'verification_sent_at' => null,
        ]);
    }
}
