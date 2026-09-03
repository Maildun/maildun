<?php

namespace Database\Factories;

use App\Enums\AudienceAttributeType;
use App\Models\Audience;
use App\Models\AudienceAttribute;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<AudienceAttribute>
 */
class AudienceAttributeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->word().' '.fake()->word();

        return [
            'audience_id' => Audience::factory(),
            'name' => Str::headline($name),
            'key' => AudienceAttribute::keyFromName($name),
            'type' => AudienceAttributeType::Text,
        ];
    }

    public function number(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => AudienceAttributeType::Number,
        ]);
    }

    public function date(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => AudienceAttributeType::Date,
        ]);
    }
}
