<?php

namespace App\Models;

use Database\Factories\EmailLinkTrackingAggregateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $email_link_id
 * @property int $total_clicks_count
 * @property int $unique_clicks_count
 * @property int $revision
 * @property Carbon|null $first_clicked_at
 * @property Carbon|null $last_clicked_at
 * @property-read EmailLink $link
 */
#[Fillable([
    'email_link_id',
    'total_clicks_count',
    'unique_clicks_count',
    'revision',
    'first_clicked_at',
    'last_clicked_at',
])]
class EmailLinkTrackingAggregate extends Model
{
    /** @use HasFactory<EmailLinkTrackingAggregateFactory> */
    use HasFactory;

    /** @return BelongsTo<EmailLink, $this> */
    public function link(): BelongsTo
    {
        return $this->belongsTo(EmailLink::class, 'email_link_id');
    }

    protected function casts(): array
    {
        return [
            'first_clicked_at' => 'datetime',
            'last_clicked_at' => 'datetime',
        ];
    }
}
