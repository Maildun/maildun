<?php

namespace App\Models;

use Database\Factories\TagFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $uuid
 * @property int $team_id
 * @property string $name
 * @property string|null $color
 * @property int $contacts_count
 * @property int $subscribers_count
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Team $team
 */
#[Fillable(['team_id', 'name', 'color'])]
class Tag extends Model
{
    /** @use HasFactory<TagFactory> */
    use HasFactory;

    /** Curated palette used to auto-assign a color when none is given. */
    public const array COLORS = [
        '#f43f5e', '#f97316', '#eab308', '#22c55e',
        '#14b8a6', '#0ea5e9', '#6366f1', '#a855f7', '#ec4899',
    ];

    protected static function booted(): void
    {
        static::creating(function (Tag $tag): void {
            $tag->uuid ??= (string) Str::uuid();
            $tag->color ??= Arr::random(self::COLORS);
        });
    }

    /** @return BelongsTo<Team, $this> */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /** @return BelongsToMany<Contact, $this> */
    public function contacts(): BelongsToMany
    {
        return $this->belongsToMany(Contact::class)->withTimestamps();
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
