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
 * @property string|null $conversion_event_key
 * @property int $attribution_window_days
 * @property int|null $conversion_target
 * @property string|null $value_currency
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Team $team
 * @property-read Collection<int, Email> $emails
 * @property-read Collection<int, EmailConversionEvent> $conversionEvents
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
    'conversion_event_key',
    'attribution_window_days',
    'conversion_target',
    'value_currency',
])]
class CampaignSeries extends Model
{
    /** @use HasFactory<CampaignSeriesFactory> */
    use HasFactory;

    public const int DEFAULT_ATTRIBUTION_WINDOW_DAYS = 30;

    /** @var array<string, mixed> */
    protected $attributes = [
        'attribution_window_days' => self::DEFAULT_ATTRIBUTION_WINDOW_DAYS,
    ];

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

    /**
     * Conversions credited to this series, whatever the attribution type.
     *
     * @return HasMany<EmailConversionEvent, $this>
     */
    public function conversionEvents(): HasMany
    {
        return $this->hasMany(EmailConversionEvent::class);
    }

    public function tracksConversions(): bool
    {
        return filled($this->conversion_event_key);
    }

    protected function casts(): array
    {
        return [
            'goal' => CampaignSeriesGoal::class,
            'attribution_window_days' => 'integer',
            'conversion_target' => 'integer',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
