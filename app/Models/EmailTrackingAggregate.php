<?php

namespace App\Models;

use Database\Factories\EmailTrackingAggregateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $email_id
 * @property int $total_opens_count
 * @property int $unique_opens_count
 * @property int $total_clicks_count
 * @property int $unique_clicks_count
 * @property int $revision
 * @property Carbon|null $first_opened_at
 * @property Carbon|null $last_opened_at
 * @property Carbon|null $first_clicked_at
 * @property Carbon|null $last_clicked_at
 * @property-read Email $email
 */
#[Fillable([
    'email_id',
    'total_opens_count',
    'unique_opens_count',
    'total_clicks_count',
    'unique_clicks_count',
    'revision',
    'first_opened_at',
    'last_opened_at',
    'first_clicked_at',
    'last_clicked_at',
])]
class EmailTrackingAggregate extends Model
{
    /** @use HasFactory<EmailTrackingAggregateFactory> */
    use HasFactory;

    /** @return BelongsTo<Email, $this> */
    public function email(): BelongsTo
    {
        return $this->belongsTo(Email::class);
    }

    protected function casts(): array
    {
        return [
            'first_opened_at' => 'datetime',
            'last_opened_at' => 'datetime',
            'first_clicked_at' => 'datetime',
            'last_clicked_at' => 'datetime',
        ];
    }
}
