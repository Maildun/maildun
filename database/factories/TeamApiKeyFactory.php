<?php

namespace Database\Factories;

use App\Models\Team;
use App\Models\TeamApiKey;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<TeamApiKey>
 */
class TeamApiKeyFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $prefix = Str::random(12);

        return [
            'team_id' => Team::factory(),
            'name' => fake()->words(2, true),
            'prefix' => $prefix,
            'token_hash' => hash('sha256', TeamApiKey::TOKEN_PREFIX.$prefix.'_'.Str::random(40)),
        ];
    }
}
