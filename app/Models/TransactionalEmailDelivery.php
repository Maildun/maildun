<?php

namespace App\Models;

use App\Enums\EmailDeliveryStatus;
use Database\Factories\TransactionalEmailDeliveryFactory;
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
 * @property int $transactional_email_id
 * @property int|null $team_api_key_id
 * @property string|null $idempotency_key
 * @property string|null $request_hash
 * @property string $to_address
 * @property string $subject
 * @property string $html
 * @property string $from_name
 * @property string $from_address
 * @property string|null $reply_to
 * @property EmailDeliveryStatus $status
 * @property string $provider
 * @property bool $uses_team_email_integration
 * @property string|null $provider_message_id
 * @property string|null $failure_reason
 * @property Carbon|null $send_attempted_at
 * @property Carbon|null $sent_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Team $team
 * @property-read TeamApiKey|null $apiKey
 * @property-read TransactionalEmail $transactionalEmail
 */
#[Fillable([
    'team_id',
    'transactional_email_id',
    'team_api_key_id',
    'idempotency_key',
    'request_hash',
    'to_address',
    'subject',
    'html',
    'from_name',
    'from_address',
    'reply_to',
    'status',
    'provider',
    'uses_team_email_integration',
    'provider_message_id',
    'failure_reason',
    'send_attempted_at',
    'sent_at',
])]
class TransactionalEmailDelivery extends Model
{
    /** @use HasFactory<TransactionalEmailDeliveryFactory> */
    use HasFactory;

    protected $attributes = [
        'status' => EmailDeliveryStatus::Queued->value,
    ];

    protected static function booted(): void
    {
        static::creating(function (TransactionalEmailDelivery $delivery): void {
            $delivery->uuid ??= (string) Str::uuid();
        });
    }

    /** @return BelongsTo<Team, $this> */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /** @return BelongsTo<TeamApiKey, $this> */
    public function apiKey(): BelongsTo
    {
        return $this->belongsTo(TeamApiKey::class, 'team_api_key_id');
    }

    /** @return BelongsTo<TransactionalEmail, $this> */
    public function transactionalEmail(): BelongsTo
    {
        return $this->belongsTo(TransactionalEmail::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => EmailDeliveryStatus::class,
            'uses_team_email_integration' => 'boolean',
            'send_attempted_at' => 'datetime',
            'sent_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
