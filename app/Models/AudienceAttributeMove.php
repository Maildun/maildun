<?php

namespace App\Models;

use App\Jobs\MoveAudienceAttributeValues;
use Database\Factories\AudienceAttributeMoveFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A MoveAudienceAttributeValues job that has not finished yet. While it is
 * pending, members still hold values under from_key, so no other field may
 * take that key: the move would carry off or delete values written for it.
 *
 * @property int $id
 * @property int $audience_id
 * @property int|null $audience_attribute_id
 * @property string $from_key
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Audience $audience
 * @property-read AudienceAttribute|null $audienceAttribute
 */
#[Fillable(['audience_id', 'audience_attribute_id', 'from_key'])]
class AudienceAttributeMove extends Model
{
    /** @use HasFactory<AudienceAttributeMoveFactory> */
    use HasFactory;

    /**
     * Moves of this key that still hold it, other than the given field's own:
     * renaming a field back to the key it is moving off is safe, because the
     * move then has nothing to do. A move older than the job's retry window
     * has no job left to finish it, so it no longer holds the key.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeHoldingKey(Builder $query, int $audienceId, string $key, ?AudienceAttribute $except = null): void
    {
        $query
            ->where('audience_id', $audienceId)
            ->where('from_key', $key)
            ->where('created_at', '>', now()->subHours(MoveAudienceAttributeValues::RETRY_WINDOW_HOURS))
            ->when($except !== null, fn (Builder $moves) => $moves->where(fn (Builder $moves) => $moves
                ->whereNull('audience_attribute_id')
                ->orWhere('audience_attribute_id', '!=', $except->id)));
    }

    /** @return BelongsTo<Audience, $this> */
    public function audience(): BelongsTo
    {
        return $this->belongsTo(Audience::class);
    }

    /** @return BelongsTo<AudienceAttribute, $this> */
    public function audienceAttribute(): BelongsTo
    {
        return $this->belongsTo(AudienceAttribute::class);
    }
}
