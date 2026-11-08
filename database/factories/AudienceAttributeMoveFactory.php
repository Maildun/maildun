<?php

namespace Database\Factories;

use App\Models\Audience;
use App\Models\AudienceAttributeMove;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AudienceAttributeMove>
 */
class AudienceAttributeMoveFactory extends Factory
{
    /**
     * A pending delete: the field is gone, its values are not yet.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'audience_id' => Audience::factory(),
            'audience_attribute_id' => null,
            'from_key' => fake()->unique()->word(),
        ];
    }
}
