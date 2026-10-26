<?php

namespace App\Models;

use Database\Factories\CompanyDomainFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $uuid
 * @property int $team_id
 * @property int $company_id
 * @property string $domain
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Team $team
 * @property-read Company $company
 */
#[Fillable(['team_id', 'company_id', 'domain'])]
class CompanyDomain extends Model
{
    /** @use HasFactory<CompanyDomainFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (CompanyDomain $companyDomain): void {
            $companyDomain->uuid ??= (string) Str::uuid();
        });
    }

    /** @return BelongsTo<Team, $this> */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /** @return BelongsTo<Company, $this> */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /** @return Attribute<string, string> */
    protected function domain(): Attribute
    {
        return Attribute::make(
            set: fn (string $value): string => self::normalize($value),
        );
    }

    /**
     * Reduce what someone typed into a company domain to the ASCII email domain.
     * "https://www.Acme.com/" and "acme.com" both become "acme.com", and
     * "münchen.de" becomes its punycode form "xn--mnchen-3ya.de".
     */
    public static function normalize(string $value): string
    {
        $domain = Str::of($value)
            ->trim()
            ->lower()
            ->replaceMatches('#^[a-z][a-z0-9+.\-]*://#', '')
            ->rtrim('/')
            ->replaceMatches('/^www\./', '')
            ->toString();

        return self::toAscii($domain);
    }

    /**
     * Convert an internationalized domain to punycode so stored company domains and
     * contact email domains compare in one form. Unconvertible input is returned as is.
     */
    public static function toAscii(string $domain): string
    {
        if ($domain === '' || preg_match('/^[\x00-\x7F]+$/', $domain) === 1) {
            return $domain;
        }

        $ascii = idn_to_ascii($domain, IDNA_NONTRANSITIONAL_TO_ASCII, INTL_IDNA_VARIANT_UTS46);

        return $ascii === false ? $domain : $ascii;
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
