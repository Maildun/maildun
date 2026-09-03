<?php

namespace App\Models;

use Database\Factories\MediaTagFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $uuid
 * @property int $team_id
 * @property string $name
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Team $team
 * @property-read Collection<int, Media> $media
 */
#[Fillable(['team_id', 'name'])]
class MediaTag extends Model
{
    /** @use HasFactory<MediaTagFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (MediaTag $tag): void {
            $tag->uuid ??= (string) Str::uuid();
        });
    }

    /** @return BelongsTo<Team, $this> */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /** @return BelongsToMany<Media, $this> */
    public function media(): BelongsToMany
    {
        return $this->belongsToMany(Media::class)->withTimestamps();
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
