<?php

namespace App\Models;

use App\Enums\EmailSendRunKind;
use Database\Factories\EmailSendRunFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * One pass over a campaign's deliveries: the first send, or a deliberate
 * retry of failed deliveries. Each delivery points at the run that last
 * queued it, so a retry reports its own progress instead of the campaign's.
 *
 * @property int $id
 * @property int $email_id
 * @property EmailSendRunKind $kind
 * @property int $recipient_count
 * @property Carbon $started_at
 * @property Carbon|null $finished_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Email $email
 */
#[Fillable([
    'email_id',
    'kind',
    'recipient_count',
    'started_at',
    'finished_at',
])]
class EmailSendRun extends Model
{
    /** @use HasFactory<EmailSendRunFactory> */
    use HasFactory;

    /** @return BelongsTo<Email, $this> */
    public function email(): BelongsTo
    {
        return $this->belongsTo(Email::class);
    }

    /** @return HasMany<EmailDelivery, $this> */
    public function deliveries(): HasMany
    {
        return $this->hasMany(EmailDelivery::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'kind' => EmailSendRunKind::class,
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }
}
