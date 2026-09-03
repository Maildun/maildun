<?php

namespace App\Models;

use Database\Factories\TeamSenderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $uuid
 * @property int $team_id
 * @property string|null $name
 * @property string $email
 * @property string|null $reply_to
 * @property Carbon|null $email_verified_at
 * @property Carbon|null $verification_sent_at
 * @property int|null $verified_email_integration_id
 * @property int|null $verified_email_integration_version
 * @property int|null $verified_sender_domain_id
 * @property bool $verified_by_provider
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Team $team
 * @property-read TeamSenderDomain|null $verifiedSenderDomain
 */
#[Fillable([
    'team_id',
    'name',
    'email',
    'reply_to',
    'email_verified_at',
    'verification_sent_at',
    'verified_email_integration_id',
    'verified_email_integration_version',
    'verified_sender_domain_id',
    'verified_by_provider',
])]
class TeamSender extends Model
{
    /** @use HasFactory<TeamSenderFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (TeamSender $sender): void {
            $sender->uuid ??= (string) Str::uuid();
            $sender->email = Str::lower(trim($sender->email));
            $sender->reply_to = filled($sender->reply_to)
                ? Str::lower(trim((string) $sender->reply_to))
                : null;
        });

        static::updating(function (TeamSender $sender): void {
            if ($sender->isDirty('email')) {
                throw new \LogicException('A sender email address cannot be changed. Create and verify a new sender instead.');
            }

            if ($sender->isDirty('reply_to')) {
                $sender->reply_to = filled($sender->reply_to)
                    ? Str::lower(trim((string) $sender->reply_to))
                    : null;
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

    /** @return BelongsTo<TeamSenderDomain, $this> */
    public function verifiedSenderDomain(): BelongsTo
    {
        return $this->belongsTo(TeamSenderDomain::class, 'verified_sender_domain_id');
    }

    public function authorizeFor(
        TeamEmailIntegration $integration,
        ?TeamSenderDomain $senderDomain = null,
        bool $trustedProvider = false,
    ): void {
        if ($integration->team_id !== $this->team_id
            || ! $integration->isVerified()
            || ($trustedProvider && ($senderDomain instanceof TeamSenderDomain
                || ! $integration->trustsProviderForSender($this->email)))
            || ($senderDomain instanceof TeamSenderDomain
                && ($senderDomain->team_id !== $this->team_id
                    || ! $senderDomain->isVerifiedFor($integration)
                    || ! $senderDomain->matchesAddress($this->email)))) {
            throw new \LogicException('The sender cannot be authorized for this delivery connection.');
        }

        $this->forceFill([
            'email_verified_at' => now(),
            'verified_email_integration_id' => $integration->id,
            'verified_email_integration_version' => $integration->verification_version,
            'verified_sender_domain_id' => $senderDomain?->id,
            'verified_by_provider' => $trustedProvider,
        ])->save();
    }

    public function isVerified(): bool
    {
        $integration = $this->verifiedEmailIntegration;

        return $integration instanceof TeamEmailIntegration
            && $this->isVerifiedFor($integration);
    }

    public function isVerifiedFor(TeamEmailIntegration $integration): bool
    {
        $hasCurrentProof = $this->email_verified_at !== null
            && $integration->isVerified()
            && $this->verified_email_integration_id === $integration->id
            && $this->verified_email_integration_version === $integration->verification_version;

        if (! $hasCurrentProof) {
            return $hasCurrentProof;
        }

        if ($this->verified_by_provider) {
            return $integration->trustsProviderForSender($this->email);
        }

        if ($this->verified_sender_domain_id === null) {
            return true;
        }

        $senderDomain = $this->verifiedSenderDomain;

        return $senderDomain instanceof TeamSenderDomain
            && $senderDomain->isVerifiedFor($integration)
            && $senderDomain->matchesAddress($this->email);
    }

    /**
     * @param  Builder<TeamSender>  $query
     * @return Builder<TeamSender>
     */
    public function scopeAuthorizedForIntegration(
        Builder $query,
        TeamEmailIntegration $integration,
    ): Builder {
        if (! $integration->isVerified()) {
            return $query->whereRaw('1 = 0');
        }

        $testedDomain = TeamSenderDomain::fromEmail((string) $integration->test_from_address);

        $query = $query
            ->whereNotNull('email_verified_at')
            ->where('verified_email_integration_id', $integration->id)
            ->where('verified_email_integration_version', $integration->verification_version)
            ->where(function (Builder $query) use ($integration, $testedDomain): void {
                $query->where(function (Builder $query): void {
                    $query->where('verified_by_provider', false)
                        ->whereNull('verified_sender_domain_id');
                });

                if ($integration->trust_provider_senders && $testedDomain !== null) {
                    $query->orWhere(function (Builder $query) use ($testedDomain): void {
                        $query->where('verified_by_provider', true)
                            ->whereNull('verified_sender_domain_id')
                            ->where('email', 'like', '%@'.$testedDomain);
                    });
                }

                if ($testedDomain !== null) {
                    $query->orWhereHas(
                        'verifiedSenderDomain',
                        fn (Builder $domainQuery): Builder => $domainQuery
                            ->whereNotNull('verified_at')
                            ->where('domain', $testedDomain)
                            ->where('verified_email_integration_id', $integration->id)
                            ->where('verified_email_integration_version', $integration->verification_version),
                    );
                }
            });

        return $query;
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'verification_sent_at' => 'datetime',
            'verified_email_integration_id' => 'integer',
            'verified_email_integration_version' => 'integer',
            'verified_sender_domain_id' => 'integer',
            'verified_by_provider' => 'boolean',
        ];
    }
}
