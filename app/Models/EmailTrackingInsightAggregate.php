<?php

namespace App\Models;

use App\Enums\EmailTrackingClassification;
use App\Enums\EmailTrackingInsightDimension;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $email_id
 * @property EmailTrackingClassification $classification
 * @property EmailTrackingInsightDimension $dimension
 * @property string $dimension_key
 * @property string $dimension_label
 * @property int $total_opens_count
 * @property int $unique_opens_count
 * @property int $total_clicks_count
 * @property int $unique_clicks_count
 * @property Carbon|null $first_opened_at
 * @property Carbon|null $last_opened_at
 * @property Carbon|null $first_clicked_at
 * @property Carbon|null $last_clicked_at
 * @property-read Email $email
 */
#[Fillable([
    'email_id',
    'classification',
    'dimension',
    'dimension_key',
    'dimension_label',
    'total_opens_count',
    'unique_opens_count',
    'total_clicks_count',
    'unique_clicks_count',
    'first_opened_at',
    'last_opened_at',
    'first_clicked_at',
    'last_clicked_at',
])]
class EmailTrackingInsightAggregate extends Model
{
    /** @return BelongsTo<Email, $this> */
    public function email(): BelongsTo
    {
        return $this->belongsTo(Email::class);
    }

    /** @return HasMany<EmailTrackingInsightUnique, $this> */
    public function uniques(): HasMany
    {
        return $this->hasMany(EmailTrackingInsightUnique::class);
    }

    protected function casts(): array
    {
        return [
            'classification' => EmailTrackingClassification::class,
            'dimension' => EmailTrackingInsightDimension::class,
            'first_opened_at' => 'datetime',
            'last_opened_at' => 'datetime',
            'first_clicked_at' => 'datetime',
            'last_clicked_at' => 'datetime',
        ];
    }
}
