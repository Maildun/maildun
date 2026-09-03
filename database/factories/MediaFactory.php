<?php

namespace Database\Factories;

use App\Enums\MediaStatus;
use App\Models\Media;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Media>
 */
class MediaFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $uuid = (string) Str::uuid();
        $teamUuid = (string) Str::uuid();

        return [
            'team_id' => Team::factory(),
            'name' => fake()->unique()->word().'.png',
            'alt' => null,
            'disk' => 'public',
            'path' => 'media/'.$teamUuid.'/'.$uuid.'.png',
            'upload_path' => null,
            'mime_type' => 'image/png',
            'extension' => 'png',
            'size' => 12_345,
            'width' => 800,
            'height' => 600,
            'status' => MediaStatus::Ready,
            'failed_reason' => null,
        ];
    }

    public function processing(): static
    {
        return $this->state(fn (array $attributes): array => [
            'disk' => 'local',
            'path' => null,
            'upload_path' => 'media/'.Str::uuid().'/pending/'.Str::uuid().'.png',
            'status' => MediaStatus::Processing,
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'disk' => null,
            'path' => null,
            'upload_path' => null,
            'status' => MediaStatus::Failed,
            'failed_reason' => 'Unable to convert the image to WebP.',
        ]);
    }
}
