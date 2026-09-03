<?php

namespace App\Models;

use App\Enums\EmailEditor;
use App\Enums\EmailStatus;
use Database\Factories\EmailFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $uuid
 * @property int $team_id
 * @property int|null $audience_id
 * @property int|null $segment_id
 * @property int|null $email_template_id
 * @property string $name
 * @property string $subject
 * @property string|null $preheader
 * @property string|null $from_name
 * @property string|null $from_address
 * @property string|null $reply_to
 * @property EmailEditor $editor
 * @property EmailStatus $status
 * @property string|null $html
 * @property string|null $source
 * @property string|null $plain_text
 * @property string|null $query_string
 * @property-read EmailTrackingAggregate|null $trackingAggregate
 * @property-read Collection<int, EmailTrackingInsightAggregate> $insightAggregates
 * @property bool $track_clicks
 * @property bool $track_opens
 * @property array<string, mixed>|null $design
 * @property string|null $batch_id
 * @property int $recipient_count
 * @property Carbon|null $last_tested_at
 * @property Carbon|null $send_started_at
 * @property Carbon|null $sent_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Team $team
 * @property-read Audience|null $audience
 * @property-read Segment|null $segment
 * @property-read EmailTemplate|null $template
 * @property-read Collection<int, EmailAttachment> $attachments
 */
#[Fillable([
    'team_id',
    'audience_id',
    'segment_id',
    'email_template_id',
    'name',
    'subject',
    'preheader',
    'from_name',
    'from_address',
    'reply_to',
    'editor',
    'html',
    'source',
    'plain_text',
    'query_string',
    'track_clicks',
    'track_opens',
    'design',
    'status',
    'batch_id',
    'recipient_count',
    'send_started_at',
    'sent_at',
])]
class Email extends Model
{
    /** @use HasFactory<EmailFactory> */
    use HasFactory, SoftDeletes;

    protected $attributes = [
        'editor' => EmailEditor::Html->value,
        'status' => EmailStatus::Draft->value,
        'track_clicks' => true,
        'track_opens' => true,
    ];

    protected static function booted(): void
    {
        static::creating(function (Email $email): void {
            $email->uuid ??= (string) Str::uuid();
        });
    }

    /** @return BelongsTo<Team, $this> */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /** @return BelongsTo<Audience, $this> */
    public function audience(): BelongsTo
    {
        return $this->belongsTo(Audience::class);
    }

    /** @return BelongsTo<Segment, $this> */
    public function segment(): BelongsTo
    {
        return $this->belongsTo(Segment::class);
    }

    /** @return BelongsTo<EmailTemplate, $this> */
    public function template(): BelongsTo
    {
        return $this->belongsTo(EmailTemplate::class, 'email_template_id');
    }

    /** @return HasMany<EmailDelivery, $this> */
    public function deliveries(): HasMany
    {
        return $this->hasMany(EmailDelivery::class);
    }

    /** @return HasMany<EmailLink, $this> */
    public function links(): HasMany
    {
        return $this->hasMany(EmailLink::class);
    }

    /** @return HasOne<EmailTrackingAggregate, $this> */
    public function trackingAggregate(): HasOne
    {
        return $this->hasOne(EmailTrackingAggregate::class);
    }

    /** @return HasMany<EmailTrackingInsightAggregate, $this> */
    public function insightAggregates(): HasMany
    {
        return $this->hasMany(EmailTrackingInsightAggregate::class);
    }

    /** @return HasMany<EmailAttachment, $this> */
    public function attachments(): HasMany
    {
        return $this->hasMany(EmailAttachment::class);
    }

    /**
     * The address the email is sent from, falling back through the audience,
     * then the team, then the application default.
     */
    public function resolvedFromAddress(): string
    {
        return $this->from_address
            ?? $this->audience->from_address
            ?? $this->team->email_from_address
            ?? (string) config('mail.from.address');
    }

    /**
     * The display name the email is sent from, falling back the same way.
     */
    public function resolvedFromName(): string
    {
        return $this->from_name
            ?? $this->audience->from_name
            ?? $this->team->email_from_name
            ?? (string) config('mail.from.name');
    }

    public function resolvedReplyTo(): ?string
    {
        return $this->reply_to
            ?? $this->audience->reply_to
            ?? $this->team->email_reply_to;
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'editor' => EmailEditor::class,
            'status' => EmailStatus::class,
            'design' => 'array',
            'track_clicks' => 'boolean',
            'track_opens' => 'boolean',
            'last_tested_at' => 'datetime',
            'send_started_at' => 'datetime',
            'sent_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
