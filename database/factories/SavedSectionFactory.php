<?php

namespace Database\Factories;

use App\Models\SavedSection;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SavedSection>
 */
class SavedSectionFactory extends Factory
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
            'name' => fake()->randomElement(['Header', 'Footer', 'Logo', 'Signature']),
            'blocks' => [
                [
                    'type' => 'container',
                    'children' => [
                        ['type' => 'text', 'props' => ['markdown' => fake()->sentence()]],
                    ],
                ],
            ],
        ];
    }
}
