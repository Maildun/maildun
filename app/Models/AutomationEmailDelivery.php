<?php

namespace App\Models;

use App\Enums\EmailDeliveryStatus;
use App\Enums\EmailProvider;
use Database\Factories\AutomationEmailDeliveryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $uuid
 * @property int $team_id
 * @property int|null $automation_run_id
 * @property int|null $automation_run_step_id
 * @property int|null $transactional_email_id
 * @property int|null $subscriber_id
 * @property string|null $subscriber_uuid
 * @property int|null $audience_id
 * @property int|null $contact_id
 * @property string $to_address
 * @property EmailDeliveryStatus $status
 * @property EmailProvider $provider
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
 * @property-read Team $team
 * @property-read AutomationRun|null $run
 * @property-read AutomationRunStep|null $step
 * @property-read TransactionalEmail|null $transactionalEmail
 * @property-read Subscriber|null $subscriber
 * @property-read Audience|null $audience
 */
#[Fillable([
    'team_id', 'automation_run_id', 'automation_run_step_id', 'transactional_email_id', 'subscriber_id',
    'subscriber_uuid', 'audience_id', 'contact_id',
    'to_address', 'status', 'provider', 'provider_message_id', 'failure_reason',
    'ses_configuration_set', 'ses_sns_topic_arn_hash', 'send_attempted_at',
    'sent_at', 'delivered_at', 'delayed_at', 'bounced_at', 'complained_at',
])]
class AutomationEmailDelivery extends Model
{
    /** @use HasFactory<AutomationEmailDeliveryFactory> */
    use HasFactory;

    /** @var array<string, mixed> */
    protected $attributes = [
        'status' => EmailDeliveryStatus::Sending->value,
    ];

    protected static function booted(): void
    {
        static::creating(function (AutomationEmailDelivery $delivery): void {
            $delivery->uuid ??= (string) Str::uuid();
        });
    }

    /** @return BelongsTo<Team, $this> */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /** @return BelongsTo<AutomationRun, $this> */
    public function run(): BelongsTo
    {
        return $this->belongsTo(AutomationRun::class, 'automation_run_id');
    }

    /**
     * The send step this email belongs to. Null on deliveries written before
     * automation sends moved to their own job.
     *
     * @return BelongsTo<AutomationRunStep, $this>
     */
    public function step(): BelongsTo
    {
        return $this->belongsTo(AutomationRunStep::class, 'automation_run_step_id');
    }

    /** @return BelongsTo<TransactionalEmail, $this> */
    public function transactionalEmail(): BelongsTo
    {
        return $this->belongsTo(TransactionalEmail::class);
    }

    /** @return BelongsTo<Subscriber, $this> */
    public function subscriber(): BelongsTo
    {
        return $this->belongsTo(Subscriber::class);
    }

    /** @return BelongsTo<Audience, $this> */
    public function audience(): BelongsTo
    {
        return $this->belongsTo(Audience::class);
    }

    protected function casts(): array
    {
        return [
            'status' => EmailDeliveryStatus::class,
            'provider' => EmailProvider::class,
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
