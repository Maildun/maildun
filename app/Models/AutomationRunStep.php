<?php

namespace App\Models;

use App\Enums\AutomationRunStatus;
use Carbon\CarbonInterface;
use Database\Factories\AutomationRunStepFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
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
 * @property int $automation_run_id
 * @property string $node_id
 * @property string $status
 * @property array<string, mixed>|null $result
 * @property Carbon|null $scheduled_at
 * @property Carbon|null $processed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read AutomationRun $run
 * @property-read Collection<int, AutomationEmailDelivery> $emailDeliveries
 */
#[Fillable([
    'automation_run_id',
    'node_id',
    'status',
    'result',
    'scheduled_at',
    'processed_at',
])]
class AutomationRunStep extends Model
{
    /** @use HasFactory<AutomationRunStepFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'completed',
    ];

    protected static function booted(): void
    {
        static::creating(function (AutomationRunStep $step): void {
            $step->uuid ??= (string) Str::uuid();
        });
    }

    /** @return BelongsTo<AutomationRun, $this> */
    public function run(): BelongsTo
    {
        return $this->belongsTo(AutomationRun::class, 'automation_run_id');
    }

    /** @return HasMany<AutomationEmailDelivery, $this> */
    public function emailDeliveries(): HasMany
    {
        return $this->hasMany(AutomationEmailDelivery::class);
    }

    /**
     * Branches of an open run that a worker should pick up now: ones waiting
     * for their first attempt, and delays whose time has come.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeDueForDispatch(Builder $query): void
    {
        $query
            ->whereHas('run', fn (Builder $runs) => $runs->whereIn('status', AutomationRunStatus::open()))
            ->where(function (Builder $steps): void {
                $steps->where('status', 'pending')
                    ->orWhere(function (Builder $steps): void {
                        $steps->where('status', 'waiting')
                            ->whereNotNull('scheduled_at')
                            ->where('scheduled_at', '<=', now());
                    });
            });
    }

    /**
     * Branches that have not moved since the given time and, for a delay, were
     * already due by then: their job had its chance and was probably lost.
     * A branch still waiting in a busy queue is touched each time it is
     * queued again, so it is not queued once more on every sweep.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeStalledSince(Builder $query, CarbonInterface $before): void
    {
        $query
            ->where('updated_at', '<=', $before)
            ->where(fn (Builder $steps) => $steps->whereNull('scheduled_at')->orWhere('scheduled_at', '<=', $before));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'result' => 'array',
            'scheduled_at' => 'datetime',
            'processed_at' => 'datetime',
        ];
    }
}
