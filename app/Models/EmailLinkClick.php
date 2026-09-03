<?php

namespace App\Models;

use Database\Factories\EmailLinkClickFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $email_delivery_id
 * @property int $email_link_id
 * @property int $clicks_count
 * @property Carbon $first_clicked_at
 * @property Carbon $last_clicked_at
 */
#[Fillable(['email_delivery_id', 'email_link_id', 'clicks_count', 'first_clicked_at', 'last_clicked_at'])]
class EmailLinkClick extends Model
{
    /** @use HasFactory<EmailLinkClickFactory> */
    use HasFactory;

    /** @return BelongsTo<EmailDelivery, $this> */
    public function delivery(): BelongsTo
    {
        return $this->belongsTo(EmailDelivery::class, 'email_delivery_id');
    }

    /** @return BelongsTo<EmailLink, $this> */
    public function link(): BelongsTo
    {
        return $this->belongsTo(EmailLink::class, 'email_link_id');
    }

    protected function casts(): array
    {
        return ['first_clicked_at' => 'datetime', 'last_clicked_at' => 'datetime'];
    }
}
