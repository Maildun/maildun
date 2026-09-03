<?php

namespace App\Models;

use App\Services\DiceBearAvatarGenerator;
use Database\Factories\CompanyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $uuid
 * @property int $team_id
 * @property string $name
 * @property string $normalized_name
 * @property string|null $favicon
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Team $team
 * @property-read Collection<int, CompanyDomain> $domains
 * @property-read Collection<int, Contact> $contacts
 */
#[Fillable(['team_id', 'name'])]
class Company extends Model
{
    /** @use HasFactory<CompanyFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (Company $company): void {
            $company->uuid ??= (string) Str::uuid();
        });
    }

    /** @return BelongsTo<Team, $this> */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /** @return HasMany<CompanyDomain, $this> */
    public function domains(): HasMany
    {
        return $this->hasMany(CompanyDomain::class);
    }

    /** @return HasMany<Contact, $this> */
    public function contacts(): HasMany
    {
        return $this->hasMany(Contact::class);
    }

    /** @return Attribute<string, string> */
    protected function name(): Attribute
    {
        return Attribute::make(
            set: fn (string $value): array => [
                'name' => Str::squish($value),
                'normalized_name' => Str::lower(Str::squish($value)),
            ],
        );
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public static function faviconUrlFor(string $domain): string
    {
        return 'https://www.google.com/s2/favicons?domain='.rawurlencode($domain).'&sz=64';
    }

    public function fallbackFaviconUrl(): string
    {
        return URL::signedRoute('avatars.show', [
            'style' => DiceBearAvatarGenerator::LOOPS,
            'seed' => $this->uuid,
        ], absolute: false);
    }
}
