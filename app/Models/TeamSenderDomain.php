<?php

namespace App\Models;

use Database\Factories\TeamSenderDomainFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $uuid
 * @property int $team_id
 * @property string $domain
 * @property string $verification_token
 * @property Carbon|null $verified_at
 * @property Carbon|null $verification_checked_at
 * @property int|null $verified_email_integration_id
 * @property int|null $verified_email_integration_version
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Team $team
 * @property-read TeamEmailIntegration|null $verifiedEmailIntegration
 */
#[Fillable([
    'team_id',
    'domain',
    'verification_token',
    'verified_at',
    'verification_checked_at',
    'verified_email_integration_id',
    'verified_email_integration_version',
])]
class TeamSenderDomain extends Model
{
    /** @use HasFactory<TeamSenderDomainFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (TeamSenderDomain $senderDomain): void {
            $senderDomain->uuid ??= (string) Str::uuid();
            $senderDomain->domain = self::normalize($senderDomain->domain);
            $senderDomain->verification_token ??= Str::random(48);
        });

        static::updating(function (TeamSenderDomain $senderDomain): void {
            if ($senderDomain->isDirty('domain')) {
                throw new \LogicException('A verified sender domain cannot be changed. Add a new domain instead.');
            }
        });
    }

    /** @return BelongsTo<Team, $this> */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /** @return BelongsTo<TeamEmailIntegration, $this> */
    public function verifiedEmailIntegration(): BelongsTo
    {
        return $this->belongsTo(TeamEmailIntegration::class, 'verified_email_integration_id');
    }

    /** @return HasMany<TeamSender, $this> */
    public function senders(): HasMany
    {
        return $this->hasMany(TeamSender::class, 'verified_sender_domain_id');
    }

    public function isVerifiedFor(TeamEmailIntegration $integration): bool
    {
        return $this->verified_at !== null
            && $integration->isVerified()
            && $this->matchesAddress((string) $integration->test_from_address)
            && $this->verified_email_integration_id === $integration->id
            && $this->verified_email_integration_version === $integration->verification_version;
    }

    public function dnsRecordName(): string
    {
        return '_maildun-verification.'.$this->domain;
    }

    public function dnsRecordValue(): string
    {
        return 'maildun-verification='.$this->verification_token;
    }

    public function matchesAddress(string $address): bool
    {
        return self::fromEmail($address) === $this->domain;
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public static function normalize(string $domain): string
    {
        return Str::lower(rtrim(trim($domain), '.'));
    }

    public static function fromEmail(string $address): ?string
    {
        $atPosition = strrpos(trim($address), '@');

        if ($atPosition === false) {
            return null;
        }

        $domain = self::normalize(substr($address, $atPosition + 1));

        return $domain === '' ? null : $domain;
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'verified_at' => 'datetime',
            'verification_checked_at' => 'datetime',
            'verified_email_integration_id' => 'integer',
            'verified_email_integration_version' => 'integer',
        ];
    }
}
