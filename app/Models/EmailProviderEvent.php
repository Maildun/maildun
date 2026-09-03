<?php

namespace App\Models;

use Database\Factories\EmailProviderEventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'provider',
    'ses_sns_topic_arn_hash',
    'event_id',
    'email_delivery_id',
    'email_delivery_attempt_id',
    'type',
    'payload',
    'occurred_at',
    'processed_at',
])]
class EmailProviderEvent extends Model
{
    /** @use HasFactory<EmailProviderEventFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    /** @return BelongsTo<EmailDelivery, $this> */
    public function delivery(): BelongsTo
    {
        return $this->belongsTo(EmailDelivery::class, 'email_delivery_id');
    }

    /** @return BelongsTo<EmailDeliveryAttempt, $this> */
    public function attempt(): BelongsTo
    {
        return $this->belongsTo(EmailDeliveryAttempt::class, 'email_delivery_attempt_id');
    }

    protected function casts(): array
    {
        return ['payload' => 'array', 'occurred_at' => 'datetime', 'processed_at' => 'datetime'];
    }
}
