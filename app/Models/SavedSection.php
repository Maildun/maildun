<?php

namespace App\Models;

use Database\Factories\SavedSectionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Blocks an author saved from a design (a header with the logo, a footer with
 * the address) to reuse in any block email in the workspace. Blocks are
 * stored without ids, so every insert gets fresh ones.
 *
 * @property int $id
 * @property string $uuid
 * @property int $team_id
 * @property int|null $created_by
 * @property string $name
 * @property list<array<string, mixed>> $blocks
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Team $team
 * @property-read User|null $creator
 */
#[Fillable(['team_id', 'created_by', 'name', 'blocks'])]
class SavedSection extends Model
{
    /** @use HasFactory<SavedSectionFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (SavedSection $section): void {
            $section->uuid ??= (string) Str::uuid();
        });
    }

    /** @return BelongsTo<Team, $this> */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Newest first, the order the editor's palette lists them in.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeNewestFirst(Builder $query): void
    {
        $query->latest()->latest('id');
    }

    /**
     * @return array{id: string, name: string, blocks: list<array<string, mixed>>}
     */
    public function toEditorArray(): array
    {
        return [
            'id' => $this->uuid,
            'name' => $this->name,
            'blocks' => $this->blocks,
        ];
    }

    /**
     * The workspace's saved sections in the shape the block editor's palette takes.
     *
     * @return list<array{id: string, name: string, blocks: list<array<string, mixed>>}>
     */
    public static function forEditor(Team $team): array
    {
        return array_values($team->savedSections()
            ->newestFirst()
            ->get()
            ->map(fn (SavedSection $section): array => $section->toEditorArray())
            ->all());
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'blocks' => 'array',
        ];
    }
}
