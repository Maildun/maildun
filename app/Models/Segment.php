<?php

namespace App\Models;

use App\Enums\EmailStatus;
use App\Enums\SegmentMatchType;
use Database\Factories\SegmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $uuid
 * @property int $audience_id
 * @property string $name
 * @property string|null $description
 * @property SegmentMatchType $match_type
 * @property list<array{field: string, operator: string, value: string}> $rules
 * @property Carbon|null $rules_synced_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Audience $audience
 * @property int $subscribers_count
 * @property int $sendable_count
 * @property int $subscribed_count
 */
#[Fillable(['audience_id', 'name', 'description', 'match_type', 'rules'])]
class Segment extends Model
{
    /** @use HasFactory<SegmentFactory> */
    use HasFactory;

    protected $attributes = [
        'match_type' => SegmentMatchType::All->value,
    ];

    protected static function booted(): void
    {
        static::creating(function (Segment $segment): void {
            $segment->uuid ??= (string) Str::uuid();
        });
    }

    /** @return BelongsTo<Audience, $this> */
    public function audience(): BelongsTo
    {
        return $this->belongsTo(Audience::class);
    }

    /** @return BelongsToMany<Subscriber, $this> */
    public function subscribers(): BelongsToMany
    {
        return $this->belongsToMany(Subscriber::class)->withTimestamps();
    }

    /** @return HasMany<Email, $this> */
    public function emails(): HasMany
    {
        return $this->hasMany(Email::class);
    }

    /**
     * Names of the campaigns targeting this segment that are in one of the statuses.
     *
     * @param  list<EmailStatus>  $statuses
     * @return Collection<int, string>
     */
    public function campaignNamesWithStatus(array $statuses): Collection
    {
        return $this->emails()
            ->whereIn('status', $statuses)
            ->orderBy('name')
            ->pluck('name');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'match_type' => SegmentMatchType::class,
            'rules' => 'array',
            'rules_synced_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
