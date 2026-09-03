<?php

namespace App\Models;

use App\Enums\EmailDeliveryStatus;
use App\Enums\SubscriberStatus;
use Database\Factories\EmailDeliveryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
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

    public function isRetryable(): bool
    {
        if (! $this->status->isRetryable()) {
            return false;
        }

        return $this->subscriber === null
            || $this->subscriber->status === SubscriberStatus::Subscribed;
    }
}
