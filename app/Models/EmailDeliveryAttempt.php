<?php

namespace App\Models;

use App\Enums\EmailDeliveryStatus;
use App\Enums\EmailProvider;
use Database\Factories\EmailDeliveryAttemptFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $uuid
 * @property int $email_delivery_id
 * @property int|null $team_email_integration_id
 * @property string|null $integration_uuid
 * @property string|null $integration_name
 * @property EmailProvider $provider
 * @property EmailDeliveryStatus $status
 * @property string|null $provider_message_id
 * @property string|null $failure_reason
 * @property string|null $ses_configuration_set
 * @property string|null $ses_sns_topic_arn_hash
 * @property Carbon|null $send_attempted_at
 * @property Carbon|null $sent_at
 * @property Carbon|null $delivered_at
 * @property Carbon|null $delayed_at
 * @property Carbon|null $bounced_at
 * @property Carbon|null $complained_at
 * @property-read EmailDelivery $delivery
 * @property-read TeamEmailIntegration|null $integration
 */
#[Fillable([
    'email_delivery_id',
    'team_email_integration_id',
    'integration_uuid',
    'integration_name',
    'provider',
    'status',
    'provider_message_id',
    'failure_reason',
    'ses_configuration_set',
    'ses_sns_topic_arn_hash',
    'send_attempted_at',
    'sent_at',
    'delivered_at',
    'delayed_at',
    'bounced_at',
    'complained_at',
])]
class EmailDeliveryAttempt extends Model
{
    /** @use HasFactory<EmailDeliveryAttemptFactory> */
    use HasFactory;

    /** @var array<string, mixed> */
    protected $attributes = [
        'status' => EmailDeliveryStatus::Sending->value,
    ];

    protected static function booted(): void
    {
        static::creating(function (EmailDeliveryAttempt $attempt): void {
            $attempt->uuid ??= (string) Str::uuid();
        });
    }

    /** @return BelongsTo<EmailDelivery, $this> */
    public function delivery(): BelongsTo
    {
        return $this->belongsTo(EmailDelivery::class, 'email_delivery_id');
    }

    /** @return BelongsTo<TeamEmailIntegration, $this> */
    public function integration(): BelongsTo
    {
        return $this->belongsTo(TeamEmailIntegration::class, 'team_email_integration_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'provider' => EmailProvider::class,
            'status' => EmailDeliveryStatus::class,
            'send_attempted_at' => 'datetime',
            'sent_at' => 'datetime',
            'delivered_at' => 'datetime',
            'delayed_at' => 'datetime',
            'bounced_at' => 'datetime',
            'complained_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
