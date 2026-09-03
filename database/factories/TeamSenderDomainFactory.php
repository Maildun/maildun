<?php

namespace Database\Factories;

use App\Models\Team;
use App\Models\TeamSenderDomain;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TeamSenderDomain>
 */
class TeamSenderDomainFactory extends Factory
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
            'domain' => fake()->unique()->domainName(),
            'verification_token' => fake()->unique()->regexify('[A-Za-z0-9]{48}'),
            'verified_at' => null,
            'verification_checked_at' => null,
            'verified_email_integration_id' => null,
            'verified_email_integration_version' => null,
        ];
    }
}
