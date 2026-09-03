<?php

namespace App\Models;

use Database\Factories\AutomationRunStepFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
