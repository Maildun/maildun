<?php

namespace App\Models;

use Database\Factories\EmailLinkFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $uuid
 * @property int $email_id
 * @property string $url
 * @property string $url_hash
 * @property int $position
 * @property-read Email $email
 * @property-read EmailLinkTrackingAggregate|null $trackingAggregate
 * @property-read Collection<int, EmailTrackingEvent> $trackingEvents
 */
#[Fillable(['email_id', 'url', 'url_hash', 'position'])]
class EmailLink extends Model
{
    /** @use HasFactory<EmailLinkFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (EmailLink $link): void {
            $link->uuid ??= (string) Str::uuid();
        });
    }

    /** @return BelongsTo<Email, $this> */
    public function email(): BelongsTo
    {
        return $this->belongsTo(Email::class);
    }

    /** @return HasMany<EmailLinkClick, $this> */
    public function clicks(): HasMany
    {
        return $this->hasMany(EmailLinkClick::class);
    }

    /** @return HasOne<EmailLinkTrackingAggregate, $this> */
    public function trackingAggregate(): HasOne
    {
        return $this->hasOne(EmailLinkTrackingAggregate::class);
    }

    /** @return HasMany<EmailTrackingEvent, $this> */
    public function trackingEvents(): HasMany
    {
        return $this->hasMany(EmailTrackingEvent::class);
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
