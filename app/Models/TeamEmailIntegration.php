<?php

namespace App\Models;

use App\Enums\EmailProvider;
use App\Services\PublicSmtpHostGuard;
use Database\Factories\TeamEmailIntegrationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
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
 * @property EmailProvider $provider
 * @property string|null $name
 * @property array<string, mixed> $settings
 * @property Carbon|null $connected_at
 * @property Carbon|null $last_tested_at
 * @property int $verification_version
 * @property string|null $test_from_address
 * @property bool $trust_provider_senders
 * @property string|null $ses_sns_topic_arn_hash
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Team $team
 */
#[Fillable([
    'team_id',
    'provider',
    'name',
    'settings',
    'connected_at',
    'last_tested_at',
    'verification_version',
    'test_from_address',
    'trust_provider_senders',
])]
#[Hidden(['settings', 'ses_sns_topic_arn_hash'])]
class TeamEmailIntegration extends Model
{
    /** @use HasFactory<TeamEmailIntegrationFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (TeamEmailIntegration $integration): void {
            $integration->uuid ??= (string) Str::uuid();
        });

        static::saving(function (TeamEmailIntegration $integration): void {
            $topicArn = $integration->provider === EmailProvider::AmazonSes
                ? $integration->settings['sns_topic_arn'] ?? null
                : null;

            $integration->ses_sns_topic_arn_hash = is_string($topicArn) && filled($topicArn)
                ? self::hashSesSnsTopicArn($topicArn)
                : null;
        });
    }

    /** @return BelongsTo<Team, $this> */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /** @return HasMany<TeamSender, $this> */
    public function verifiedSenders(): HasMany
    {
        return $this->hasMany(TeamSender::class, 'verified_email_integration_id')
            ->where('team_id', $this->team_id)
            ->authorizedForIntegration($this);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'provider' => EmailProvider::class,
            'settings' => 'encrypted:array',
            'connected_at' => 'datetime',
            'last_tested_at' => 'datetime',
            'verification_version' => 'integer',
            'trust_provider_senders' => 'boolean',
        ];
    }

    public function isSenderAuthorizedFor(?string $address): bool
    {
        if (! $this->isVerified() || blank($address)) {
            return false;
        }

        return $this->verifiedSenders()
            ->where('email', Str::lower(trim($address)))
            ->exists();
    }

    public function hasCompleteConfiguration(): bool
    {
        return match ($this->provider) {
            EmailProvider::Smtp => $this->hasCompleteSmtpConfiguration(),
            EmailProvider::AmazonSes => $this->hasCompleteSesConfiguration(),
            default => false,
        };
    }

    private function hasCompleteSmtpConfiguration(): bool
    {
        $host = $this->settings['host'] ?? null;
        $port = $this->settings['port'] ?? null;
        $encryption = $this->settings['encryption'] ?? null;

        return is_string($host)
            && PublicSmtpHostGuard::isPermittedSmtpHost($host)
            && is_int($port)
            && in_array($port, [25, 465, 587, 1025, 2525], true)
            && is_string($encryption)
            && in_array($encryption, ['tls', 'ssl', 'none'], true);
    }

    /**
     * SES is reached through its API, so a complete connection carries an IAM
     * key pair. Connections saved against the old SMTP-endpoint credentials
     * fail this check, which pauses delivery until they are re-entered.
     */
    private function hasCompleteSesConfiguration(): bool
    {
        $region = $this->settings['region'] ?? null;
        $accessKeyId = $this->settings['access_key_id'] ?? null;
        $secretAccessKey = $this->settings['secret_access_key'] ?? null;

        $configurationSet = $this->settings['configuration_set'] ?? null;
        $topicArn = $this->settings['sns_topic_arn'] ?? null;
        $topicArnHash = $this->ses_sns_topic_arn_hash;

        return is_string($region)
            && preg_match('/\A(?:af|ap|ca|eu|il|me|mx|sa|us)-[a-z0-9]+(?:-[a-z0-9]+)*-\d\z/', $region) === 1
            && is_string($accessKeyId)
            && preg_match('/\A(?:AKIA|ASIA)[A-Z0-9]{16}\z/', $accessKeyId) === 1
            && is_string($secretAccessKey)
            && preg_match('/\A[A-Za-z0-9\/+=]{40}\z/', $secretAccessKey) === 1
            && is_string($configurationSet)
            && preg_match('/\A[A-Za-z0-9_-]{1,64}\z/', $configurationSet) === 1
            && is_string($topicArn)
            && preg_match('/\Aarn:aws:sns:'.preg_quote($region, '/').':\d{12}:[A-Za-z0-9_-]+(?:\.fifo)?\z/', $topicArn) === 1
            && is_string($topicArnHash)
            && hash_equals(self::hashSesSnsTopicArn($topicArn), $topicArnHash);
    }

    public function isVerified(): bool
    {
        return $this->connected_at !== null
            && $this->last_tested_at !== null
            && $this->hasCompleteConfiguration();
    }

    public function trustsProviderForSender(string $address): bool
    {
        $testedDomain = TeamSenderDomain::fromEmail((string) $this->test_from_address);

        return $this->trust_provider_senders
            && $this->isVerified()
            && $testedDomain !== null
            && TeamSenderDomain::fromEmail($address) === $testedDomain;
    }

    public function isUsableForSending(?string $address): bool
    {
        return $this->isVerified() && $this->isSenderAuthorizedFor($address);
    }

    /** @return list<string> */
    public function verifiedSenderAddresses(): array
    {
        if (! $this->isVerified()) {
            return [];
        }

        return array_values(
            $this->verifiedSenders()
                ->get(['email'])
                ->map(fn (TeamSender $sender): string => $sender->email)
                ->all(),
        );
    }

    public static function hashSesSnsTopicArn(string $topicArn): string
    {
        return hash('sha256', Str::of($topicArn)->trim()->value());
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
