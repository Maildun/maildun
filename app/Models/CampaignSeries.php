<?php

namespace App\Models;

use App\Enums\CampaignSeriesGoal;
use Database\Factories\CampaignSeriesFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
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
 * @property string|null $description
 * @property CampaignSeriesGoal $goal
 * @property string|null $objective
 * @property string|null $primary_cta_url
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Team $team
 * @property-read Collection<int, Email> $emails
 * @property-read int $emails_count
 * @property-read int $sent_campaigns_count
 */
#[Fillable([
    'team_id',
    'name',
    'description',
    'goal',
    'objective',
    'primary_cta_url',
])]
class CampaignSeries extends Model
{
    /** @use HasFactory<CampaignSeriesFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (CampaignSeries $campaignSeries): void {
            $campaignSeries->uuid ??= (string) Str::uuid();
        });
    }

    /** @return BelongsTo<Team, $this> */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /** @return HasMany<Email, $this> */
    public function emails(): HasMany
    {
        return $this->hasMany(Email::class);
    }

    protected function casts(): array
    {
        return [
            'goal' => CampaignSeriesGoal::class,
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
