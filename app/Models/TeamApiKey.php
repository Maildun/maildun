<?php

namespace App\Models;

use Database\Factories\TeamApiKeyFactory;
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
 * @property string $name
 * @property string $prefix
 * @property string $token_hash
 * @property Carbon|null $last_used_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Team $team
 */
#[Fillable(['team_id', 'name', 'prefix', 'token_hash', 'last_used_at'])]
#[Hidden(['token_hash'])]
class TeamApiKey extends Model
{
    /** @use HasFactory<TeamApiKeyFactory> */
    use HasFactory;

    public const string TOKEN_PREFIX = 'maildun_live_';

    protected static function booted(): void
    {
        static::creating(function (TeamApiKey $apiKey): void {
            $apiKey->uuid ??= (string) Str::uuid();
        });
    }

    /**
     * Issue a new API key. The plaintext value is returned once and never stored.
     *
     * @return array{key: self, token: string}
     */
    public static function issue(Team $team, string $name): array
    {
        do {
            $prefix = Str::random(12);
        } while (self::query()->where('prefix', $prefix)->exists());

        $token = self::TOKEN_PREFIX.$prefix.'_'.Str::random(40);
        $apiKey = $team->apiKeys()->create([
            'name' => $name,
            'prefix' => $prefix,
            'token_hash' => hash('sha256', $token),
        ]);

        return ['key' => $apiKey, 'token' => $token];
    }

    public static function findToken(?string $token): ?self
    {
        if (! is_string($token) || preg_match('/\Amaildun_live_([A-Za-z0-9]{12})_[A-Za-z0-9]{40}\z/', $token, $matches) !== 1) {
            return null;
        }

        $apiKey = self::query()->with('team')->where('prefix', $matches[1])->first();

        if (! $apiKey instanceof self || ! hash_equals($apiKey->token_hash, hash('sha256', $token))) {
            return null;
        }

        return $apiKey;
    }

    public function markAsUsed(): void
    {
        if ($this->last_used_at?->isAfter(now()->subMinutes(5))) {
            return;
        }

        $this->forceFill(['last_used_at' => now()])->saveQuietly();
    }

    /** @return BelongsTo<Team, $this> */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /** @return HasMany<TransactionalEmailDelivery, $this> */
    public function transactionalEmailDeliveries(): HasMany
    {
        return $this->hasMany(TransactionalEmailDelivery::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'last_used_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
