<?php

namespace Database\Factories;

use App\Enums\CampaignSeriesGoal;
use App\Models\CampaignSeries;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CampaignSeries>
 */
class CampaignSeriesFactory extends Factory
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
            'name' => fake()->unique()->words(3, true),
            'description' => fake()->sentence(),
            'goal' => fake()->randomElement(CampaignSeriesGoal::cases()),
            'objective' => fake()->sentence(6),
            'primary_cta_url' => fake()->url(),
        ];
    }
}
