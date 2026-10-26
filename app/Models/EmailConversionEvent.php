<?php

namespace App\Models;

use App\Enums\ConversionAttributionType;
use Database\Factories\EmailConversionEventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * A sales outcome reported by an external system, with the attribution
 * decision made when it arrived. The decision is an audit record: it does not
 * move when campaigns or series settings change later.
 *
 * @property int $id
 * @property string $uuid
 * @property int $team_id
 * @property string $external_id
 * @property string $request_hash
 * @property string $event_key
 * @property string $email
 * @property int|null $subscriber_id
 * @property int|null $requested_campaign_series_id
 * @property ConversionAttributionType $attribution_type
 * @property int|null $campaign_series_id
 * @property int|null $email_id
 * @property int|null $email_delivery_id
 * @property Carbon|null $touched_at
 * @property int|null $value_minor
 * @property string|null $currency
 * @property array<string, string|int|float|bool|null>|null $metadata
 * @property Carbon $occurred_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Team $team
 * @property-read Subscriber|null $subscriber
 * @property-read CampaignSeries|null $requestedCampaignSeries
 * @property-read CampaignSeries|null $campaignSeries
 * @property-read Email|null $campaign
 * @property-read EmailDelivery|null $delivery
 */
#[Fillable([
    'team_id',
    'external_id',
    'request_hash',
    'event_key',
    'email',
    'subscriber_id',
    'requested_campaign_series_id',
    'attribution_type',
    'campaign_series_id',
    'email_id',
    'email_delivery_id',
    'touched_at',
    'value_minor',
    'currency',
    'metadata',
    'occurred_at',
])]
class EmailConversionEvent extends Model
{
    /** @use HasFactory<EmailConversionEventFactory> */
    use HasFactory;

    /**
     * Lowercase letters, digits, and underscores, starting with a letter.
     */
    public const string EVENT_KEY_PATTERN = '/\A[a-z][a-z0-9_]{0,63}\z/';

    /**
     * Three uppercase letters, the shape of an ISO 4217 code.
     */
    public const string CURRENCY_PATTERN = '/\A[A-Z]{3}\z/';

    protected static function booted(): void
    {
        static::creating(function (EmailConversionEvent $event): void {
            $event->uuid ??= (string) Str::uuid();
        });
    }

    /** @return BelongsTo<Team, $this> */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /** @return BelongsTo<Subscriber, $this> */
    public function subscriber(): BelongsTo
    {
        return $this->belongsTo(Subscriber::class);
    }

    /** @return BelongsTo<CampaignSeries, $this> */
    public function requestedCampaignSeries(): BelongsTo
    {
        return $this->belongsTo(CampaignSeries::class, 'requested_campaign_series_id');
    }

    /** @return BelongsTo<CampaignSeries, $this> */
    public function campaignSeries(): BelongsTo
    {
        return $this->belongsTo(CampaignSeries::class);
    }

    /** @return BelongsTo<Email, $this> */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Email::class, 'email_id')->withTrashed();
    }

    /** @return BelongsTo<EmailDelivery, $this> */
    public function delivery(): BelongsTo
    {
        return $this->belongsTo(EmailDelivery::class, 'email_delivery_id');
    }

    protected function casts(): array
    {
        return [
            'attribution_type' => ConversionAttributionType::class,
            'touched_at' => 'datetime',
            'value_minor' => 'integer',
            'metadata' => 'array',
            'occurred_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
