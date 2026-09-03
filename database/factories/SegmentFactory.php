<?php

namespace Database\Factories;

use App\Enums\SegmentMatchType;
use App\Models\Audience;
use App\Models\Segment;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Segment> */
class SegmentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'audience_id' => Audience::factory(),
            'name' => fake()->words(2, true),
            'description' => fake()->optional()->sentence(),
            'match_type' => SegmentMatchType::All,
            'rules' => [[
                'field' => 'status',
                'operator' => 'equals',
                'value' => 'subscribed',
            ]],
        ];
    }
}
