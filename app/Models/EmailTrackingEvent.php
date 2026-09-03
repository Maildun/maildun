<?php

namespace App\Models;

use App\Enums\EmailTrackingClassification;
use App\Enums\EmailTrackingEventType;
use Database\Factories\EmailTrackingEventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use LogicException;

/**
 * @property int $id
 * @property string $uuid
 * @property int $email_delivery_id
 * @property int|null $email_link_id
 * @property EmailTrackingEventType $type
 * @property Carbon $occurred_at
 * @property Carbon|null $processed_at
 * @property Carbon|null $last_dispatched_at
 * @property int $processing_attempts
 * @property string|null $last_error
 * @property string|null $user_agent
 * @property string|null $ip_hash
 * @property bool|null $is_bot
 * @property bool|null $is_proxy
 * @property EmailTrackingClassification $classification
 * @property string|null $classification_reason
 * @property string|null $client_family
 * @property string $device_type
 * @property string|null $country_code
 * @property string|null $subdivision_code
 * @property string|null $subdivision_name
 * @property string|null $city_name
 * @property float|null $latitude
 * @property float|null $longitude
 * @property int|null $network_asn
 * @property string|null $network_name
 * @property string|null $geolocation_source
 * @property Carbon|null $geolocated_at
 * @property-read EmailDelivery $delivery
 * @property-read EmailLink|null $link
 */
#[Fillable([
    'email_delivery_id',
    'email_link_id',
    'type',
    'occurred_at',
    'processed_at',
    'last_dispatched_at',
    'processing_attempts',
    'last_error',
    'user_agent',
    'ip_hash',
    'is_bot',
    'is_proxy',
    'classification',
    'classification_reason',
    'client_family',
    'device_type',
    'country_code',
    'subdivision_code',
    'subdivision_name',
    'city_name',
    'latitude',
    'longitude',
    'network_asn',
    'network_name',
    'geolocation_source',
    'geolocated_at',
])]
class EmailTrackingEvent extends Model
{
    /** @use HasFactory<EmailTrackingEventFactory> */
    use HasFactory;

    protected $attributes = [
        'processing_attempts' => 0,
        'classification' => EmailTrackingClassification::Unknown->value,
        'device_type' => 'unknown',
    ];

    protected static function booted(): void
    {
        static::creating(function (EmailTrackingEvent $event): void {
            $event->uuid ??= (string) Str::uuid();
        });

        static::updating(function (EmailTrackingEvent $event): void {
            $immutable = [
                'uuid',
                'email_delivery_id',
                'email_link_id',
                'type',
                'occurred_at',
                'user_agent',
                'ip_hash',
                'is_bot',
                'is_proxy',
                'classification',
                'classification_reason',
                'client_family',
                'device_type',
                'country_code',
                'subdivision_code',
                'subdivision_name',
                'city_name',
                'latitude',
                'longitude',
                'network_asn',
                'network_name',
                'geolocation_source',
                'geolocated_at',
            ];

            if ($event->isDirty($immutable)) {
                throw new LogicException('Tracking event payloads are immutable.');
            }
        });
    }

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
        return [
            'type' => EmailTrackingEventType::class,
            'occurred_at' => 'datetime',
            'processed_at' => 'datetime',
            'last_dispatched_at' => 'datetime',
            'is_bot' => 'boolean',
            'is_proxy' => 'boolean',
            'classification' => EmailTrackingClassification::class,
            'latitude' => 'float',
            'longitude' => 'float',
            'geolocated_at' => 'datetime',
        ];
    }
}
