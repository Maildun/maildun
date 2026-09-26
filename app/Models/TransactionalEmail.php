<?php

namespace App\Models;

use App\Enums\EmailEditor;
use App\Enums\TestSendStatus;
use App\Enums\TransactionalEmailStatus;
use Database\Factories\TransactionalEmailFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $uuid
 * @property int $team_id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property string $subject
 * @property string|null $preheader
 * @property string|null $from_name
 * @property string|null $from_address
 * @property string|null $reply_to
 * @property EmailEditor $editor
 * @property TransactionalEmailStatus $status
 * @property string|null $html
 * @property string|null $source
 * @property array<string, mixed>|null $design
 * @property list<array{key: string, example: string}> $variables
 * @property Carbon|null $published_at
 * @property Carbon|null $last_tested_at
 * @property TestSendStatus|null $last_test_status
 * @property string|null $last_test_recipient
 * @property string|null $last_test_error
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Team $team
 * @property-read Collection<int, TransactionalEmailDelivery> $deliveries
 */
#[Fillable([
    'team_id',
    'name',
    'slug',
    'description',
    'subject',
    'preheader',
    'from_name',
    'from_address',
    'reply_to',
    'editor',
    'status',
    'html',
    'source',
    'design',
    'variables',
    'published_at',
    'last_tested_at',
])]
class TransactionalEmail extends Model
{
    /** @use HasFactory<TransactionalEmailFactory> */
    use HasFactory, SoftDeletes;

    protected $attributes = [
        'editor' => EmailEditor::Html->value,
        'status' => TransactionalEmailStatus::Draft->value,
        'variables' => '[]',
    ];

    protected static function booted(): void
    {
        static::creating(function (TransactionalEmail $email): void {
            $email->uuid ??= (string) Str::uuid();
        });
    }

    /** @return BelongsTo<Team, $this> */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /** @return HasMany<TransactionalEmailDelivery, $this> */
    public function deliveries(): HasMany
    {
        return $this->hasMany(TransactionalEmailDelivery::class);
    }

    /**
     * Audiences that send this email as their double opt-in confirmation.
     *
     * @return HasMany<Audience, $this>
     */
    public function doubleOptInAudiences(): HasMany
    {
        return $this->hasMany(Audience::class, 'double_opt_in_email_id')
            ->where('double_opt_in', true);
    }

    /**
     * Why this email cannot be unpublished or deleted: an audience still
     * needs it to confirm new signups, which would otherwise stay pending
     * without ever receiving a link. Null when nothing depends on it.
     */
    public function doubleOptInBlockReason(): ?string
    {
        $audienceNames = $this->doubleOptInAudiences()->orderBy('name')->pluck('name');

        if ($audienceNames->isEmpty()) {
            return null;
        }

        return trans_choice(
            ':audiences uses this as its double opt-in confirmation email. Choose another confirmation email or turn off double opt-in first.|:audiences use this as their double opt-in confirmation email. Choose another confirmation email or turn off double opt-in first.',
            $audienceNames->count(),
            ['audiences' => $audienceNames->join(', ', ' and ')],
        );
    }

    public function isPublished(): bool
    {
        return $this->status === TransactionalEmailStatus::Published;
    }

    /**
     * The identifier is frozen after the first publish so a later API can
     * keep calling the same slug even if the email is unpublished.
     */
    public function slugIsFrozen(): bool
    {
        return $this->published_at !== null;
    }

    public function resolvedFromAddress(): string
    {
        return $this->from_address
            ?? $this->team->email_from_address
            ?? (string) config('mail.from.address');
    }

    public function resolvedFromName(): string
    {
        return $this->from_name
            ?? $this->team->email_from_name
            ?? (string) config('mail.from.name');
    }

    public function resolvedReplyTo(): ?string
    {
        return $this->reply_to
            ?? $this->team->email_reply_to;
    }

    /**
     * A free slug for this team, derived from a name.
     */
    public static function uniqueSlugFor(Team $team, string $name, ?int $excludeId = null): string
    {
        $base = Str::slug($name);
        $base = $base === '' ? 'email' : $base;
        $candidate = $base;
        $suffix = 2;

        while (static::slugTaken($team, $candidate, $excludeId)) {
            $candidate = $base.'-'.$suffix;
            $suffix++;
        }

        return $candidate;
    }

    /**
     * Names are unique per team, so a copy needs a free name.
     */
    public static function uniqueCopyName(Team $team, string $name): string
    {
        $candidate = mb_substr("{$name} copy", 0, 255);
        $suffix = 2;

        while ($team->transactionalEmails()->where('name', $candidate)->exists()) {
            $candidate = mb_substr("{$name} copy {$suffix}", 0, 255);
            $suffix++;
        }

        return $candidate;
    }

    public static function slugTaken(Team $team, string $slug, ?int $excludeId = null): bool
    {
        return $team->transactionalEmails()
            ->withTrashed()
            ->where('slug', $slug)
            ->when($excludeId !== null, fn ($query) => $query->where('id', '!=', $excludeId))
            ->exists();
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'editor' => EmailEditor::class,
            'status' => TransactionalEmailStatus::class,
            'design' => 'array',
            'variables' => 'array',
            'published_at' => 'datetime',
            'last_tested_at' => 'datetime',
            'last_test_status' => TestSendStatus::class,
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
