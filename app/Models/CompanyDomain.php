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
            set: fn (string $value): string => Str::lower(Str::of($value)->trim()->toString()),
        );
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
