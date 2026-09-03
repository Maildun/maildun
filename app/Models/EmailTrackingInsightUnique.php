<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $email_tracking_insight_aggregate_id
 * @property int $email_delivery_id
 * @property Carbon|null $first_opened_at
 * @property Carbon|null $first_clicked_at
 * @property-read EmailTrackingInsightAggregate $aggregate
 * @property-read EmailDelivery $delivery
 */
#[Fillable([
    'email_tracking_insight_aggregate_id',
    'email_delivery_id',
    'first_opened_at',
    'first_clicked_at',
])]
class EmailTrackingInsightUnique extends Model
{
    /** @return BelongsTo<EmailTrackingInsightAggregate, $this> */
    public function aggregate(): BelongsTo
    {
        return $this->belongsTo(EmailTrackingInsightAggregate::class, 'email_tracking_insight_aggregate_id');
    }

    /** @return BelongsTo<EmailDelivery, $this> */
    public function delivery(): BelongsTo
    {
        return $this->belongsTo(EmailDelivery::class);
    }

    protected function casts(): array
    {
        return [
            'first_opened_at' => 'datetime',
            'first_clicked_at' => 'datetime',
        ];
    }
}
