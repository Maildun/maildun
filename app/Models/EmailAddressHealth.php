<?php

namespace App\Models;

use App\Enums\EmailAddressHealthReason;
use App\Enums\EmailAddressHealthStatus;
use App\Enums\EmailProvider;
use Database\Factories\EmailAddressHealthFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $uuid
 * @property int $team_id
 * @property string $email
 * @property EmailAddressHealthStatus $status
 * @property EmailAddressHealthReason|null $reason
 * @property EmailProvider|null $provider
 * @property string|null $detail
 * @property int $hard_bounce_count
 * @property int $soft_bounce_count
 * @property int $complaint_count
 * @property int $failure_count
 * @property Carbon|null $last_event_at
 * @property Carbon|null $last_delivered_at
 * @property Carbon|null $last_failed_at
 * @property Carbon|null $suppressed_at
 * @property-read Team $team
 */
#[Fillable([
    'uuid', 'team_id', 'email', 'status', 'reason', 'provider', 'detail',
    'hard_bounce_count', 'soft_bounce_count', 'complaint_count', 'failure_count',
    'last_event_at', 'last_delivered_at', 'last_failed_at', 'suppressed_at',
])]
class EmailAddressHealth extends Model
{
    /** @use HasFactory<EmailAddressHealthFactory> */
    use HasFactory;

    /** @var array<string, mixed> */
    protected $attributes = [
        'status' => EmailAddressHealthStatus::Unknown->value,
        'hard_bounce_count' => 0,
        'soft_bounce_count' => 0,
        'complaint_count' => 0,
        'failure_count' => 0,
    ];

    protected static function booted(): void
    {
        static::creating(function (EmailAddressHealth $health): void {
            $health->uuid ??= (string) Str::uuid();
        });

        static::saving(function (EmailAddressHealth $health): void {
            $health->email = Str::lower(trim($health->email));
        });
    }

    /** @return BelongsTo<Team, $this> */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Addresses the workspace must not mail again. A permanent bounce or a
     * complaint in any audience suppresses the address in every audience.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeSuppressedFor(Builder $query, Team $team): void
    {
        $query
            ->whereBelongsTo($team)
            ->where('email_address_healths.status', EmailAddressHealthStatus::Suppressed);
    }

    protected function casts(): array
    {
        return [
            'status' => EmailAddressHealthStatus::class,
            'reason' => EmailAddressHealthReason::class,
            'provider' => EmailProvider::class,
            'hard_bounce_count' => 'integer',
            'soft_bounce_count' => 'integer',
            'complaint_count' => 'integer',
            'failure_count' => 'integer',
            'last_event_at' => 'datetime',
            'last_delivered_at' => 'datetime',
            'last_failed_at' => 'datetime',
            'suppressed_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
