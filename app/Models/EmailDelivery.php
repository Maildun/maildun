<?php

namespace App\Models;

use App\Enums\EmailDeliveryStatus;
use App\Enums\SubscriberStatus;
use Database\Factories\EmailDeliveryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $uuid
 * @property int $email_id
 * @property int|null $subscriber_id
 * @property int|null $contact_id
 * @property string $email_address
 * @property string|null $first_name
 * @property string|null $last_name
 * @property array<string, mixed>|null $merge_data
 * @property EmailDeliveryStatus $status
 * @property string $provider
 * @property bool $uses_team_email_integration
 * @property string|null $provider_message_id
 * @property string|null $failure_reason
 * @property Carbon|null $send_attempted_at
 * @property int $opens_count
 * @property int $clicks_count
 * @property Carbon|null $sent_at
 * @property Carbon|null $delivered_at
 * @property Carbon|null $delayed_at
 * @property Carbon|null $bounced_at
 * @property Carbon|null $complained_at
 * @property Carbon|null $first_opened_at
 * @property Carbon|null $last_opened_at
 * @property Carbon|null $first_clicked_at
 * @property Carbon|null $last_clicked_at
 * @property-read Email $email
 * @property-read Subscriber|null $subscriber
 * @property-read Contact|null $contact
 * @property-read Collection<int, EmailDeliveryAttempt> $attempts
 * @property-read Collection<int, EmailTrackingEvent> $trackingEvents
 * @property-read EmailDeliveryAttempt|null $latestAttempt
 */
#[Fillable([
    'email_id', 'subscriber_id', 'contact_id', 'email_address', 'first_name', 'last_name', 'merge_data',
    'status', 'provider', 'uses_team_email_integration', 'provider_message_id', 'failure_reason', 'send_attempted_at', 'sent_at',
    'delivered_at', 'delayed_at', 'bounced_at', 'complained_at',
    'first_opened_at', 'last_opened_at', 'first_clicked_at', 'last_clicked_at',
    'opens_count', 'clicks_count',
])]
class EmailDelivery extends Model
{
    /** @use HasFactory<EmailDeliveryFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (EmailDelivery $delivery): void {
            $delivery->uuid ??= (string) Str::uuid();
        });
    }

    /** @return BelongsTo<Email, $this> */
    public function email(): BelongsTo
    {
        return $this->belongsTo(Email::class);
    }

    /** @return BelongsTo<Subscriber, $this> */
    public function subscriber(): BelongsTo
    {
        return $this->belongsTo(Subscriber::class);
    }

    /** @return BelongsTo<Contact, $this> */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    /** @return HasMany<EmailLinkClick, $this> */
    public function linkClicks(): HasMany
    {
        return $this->hasMany(EmailLinkClick::class);
    }

    /** @return HasMany<EmailDeliveryAttempt, $this> */
    public function attempts(): HasMany
    {
        return $this->hasMany(EmailDeliveryAttempt::class);
    }

    /** @return HasMany<EmailTrackingEvent, $this> */
    public function trackingEvents(): HasMany
    {
        return $this->hasMany(EmailTrackingEvent::class);
    }

    /** @return HasOne<EmailDeliveryAttempt, $this> */
    public function latestAttempt(): HasOne
    {
        return $this->hasOne(EmailDeliveryAttempt::class)->latestOfMany();
    }

    protected function casts(): array
    {
        return [
            'status' => EmailDeliveryStatus::class,
            'uses_team_email_integration' => 'boolean',
            'merge_data' => 'array',
            'send_attempted_at' => 'datetime', 'sent_at' => 'datetime',
            'delivered_at' => 'datetime', 'delayed_at' => 'datetime',
            'bounced_at' => 'datetime', 'complained_at' => 'datetime',
            'first_opened_at' => 'datetime', 'last_opened_at' => 'datetime',
            'first_clicked_at' => 'datetime', 'last_clicked_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * Deliveries a deliberate retry may queue again: a retryable outcome, a
     * subscriber who is still subscribed and confirmed (or was deleted), and an address the
     * workspace has not suppressed after a permanent bounce or complaint.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeRetryableFor(Builder $query, Team $team): void
    {
        $query
            ->whereIn('email_deliveries.status', EmailDeliveryStatus::retryable())
            ->where(function (Builder $deliveries): void {
                $deliveries
                    ->whereNull('subscriber_id')
                    ->orWhereHas(
                        'subscriber',
                        fn (Builder $subscriber) => $subscriber
                            ->where('status', SubscriberStatus::Subscribed)
                            ->whereNotNull('subscribed_at'),
                    );
            })
            ->whereNotExists(
                EmailAddressHealth::query()
                    ->suppressedFor($team)
                    ->whereColumn('email_address_healths.email', 'email_deliveries.email_address'),
            );
    }

    /**
     * Failed deliveries that were claimed and handed to the transport but
     * never confirmed. The provider may have accepted them, so retrying can
     * mail the recipient twice. A refused send releases send_attempted_at
     * before it fails, so it is never counted here.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeUnconfirmed(Builder $query): void
    {
        $query
            ->where('email_deliveries.status', EmailDeliveryStatus::Failed)
            ->whereNotNull('email_deliveries.send_attempted_at');
    }

    public function isUnconfirmed(): bool
    {
        return $this->status === EmailDeliveryStatus::Failed && $this->send_attempted_at !== null;
    }

    /**
     * Whether a deliberate retry would queue this delivery again. It runs the
     * retryableFor scope so the row, the report count, and the action can
     * never disagree; use the scope directly when checking a whole list.
     */
    public function isRetryable(): bool
    {
        return static::query()
            ->whereKey($this->getKey())
            ->retryableFor($this->email->team)
            ->exists();
    }
}
