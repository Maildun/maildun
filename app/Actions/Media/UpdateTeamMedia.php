<?php

namespace App\Actions\Media;

use App\Models\Media;
use App\Models\MediaCategory;
use App\Models\Team;
use Illuminate\Support\Str;

class UpdateTeamMedia
{
    /**
     * @param  array{
     *     name: string,
     *     alt?: string|null,
     *     category?: string|null,
     *     tags?: list<string>
     * }  $data
     */
    public function handle(Team $team, Media $media, array $data): Media
    {
        $attributes = [
            'name' => $data['name'],
        ];

        if (array_key_exists('alt', $data)) {
            $attributes['alt'] = $data['alt'];
        }

        if (array_key_exists('category', $data)) {
            $attributes['media_category_id'] = $this->resolveCategory($team, $data['category'])?->id;
        }

        $media->update($attributes);

        if (array_key_exists('tags', $data)) {
            $this->syncTags($team, $media, $data['tags']);
        }

        return $media;
    }

    private function resolveCategory(Team $team, ?string $name): ?MediaCategory
    {
        $name = trim((string) $name);

        if ($name === '') {
            return null;
        }

        return $team->mediaCategories()->firstOrCreate(['name' => $name]);
    }

    /**
     * @param  list<string>  $names
     */
    private function syncTags(Team $team, Media $media, array $names): void
    {
        $tagIds = collect($names)
            ->map(fn (string $name): string => trim($name))
            ->filter()
            ->unique(fn (string $name): string => Str::lower($name))
            ->map(fn (string $name): int => $team->mediaTags()->firstOrCreate(['name' => $name])->id);

        $media->tags()->sync($tagIds);
    }
}
