<?php

namespace App\Models;

use Database\Factories\AudienceSubscriberSnapshotFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * An audience's member counts at the end of one finished day. Written once by
 * RecordAudienceSubscriberSnapshots and never updated, so the subscriber chart
 * can show past days that later deletes and resubscribes cannot rewrite.
 *
 * @property int $id
 * @property int $audience_id
 * @property Carbon $day
 * @property int $total
 * @property int $unsubscribed
 * @property int $pending
 * @property int $new
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Audience $audience
 */
#[Fillable(['audience_id', 'day', 'total', 'unsubscribed', 'pending', 'new'])]
class AudienceSubscriberSnapshot extends Model
{
    /** @use HasFactory<AudienceSubscriberSnapshotFactory> */
    use HasFactory;

    /** @return BelongsTo<Audience, $this> */
    public function audience(): BelongsTo
    {
        return $this->belongsTo(Audience::class);
    }

    protected function casts(): array
    {
        return [
            'day' => 'date',
            'total' => 'integer',
            'unsubscribed' => 'integer',
            'pending' => 'integer',
            'new' => 'integer',
        ];
    }
}
