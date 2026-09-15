<?php

namespace App\Models;

use App\Enums\AutomationRunStatus;
use Database\Factories\AutomationRunFactory;
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
 * @property int $automation_id
 * @property int $subscriber_id
 * @property AutomationRunStatus $status
 * @property array{nodes: list<array<string, mixed>>, edges: list<array<string, mixed>>} $graph
 * @property array<string, mixed> $context
 * @property string|null $current_node_id
 * @property Carbon|null $scheduled_at
 * @property Carbon|null $started_at
 * @property Carbon|null $completed_at
 * @property Carbon|null $failed_at
 * @property string|null $failure_reason
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Automation $automation
 * @property-read Subscriber $subscriber
 * @property-read Collection<int, AutomationRunStep> $steps
 * @property-read Collection<int, AutomationEmailDelivery> $emailDeliveries
 */
#[Fillable([
    'automation_id',
    'subscriber_id',
    'status',
    'graph',
    'context',
    'current_node_id',
    'scheduled_at',
    'started_at',
    'completed_at',
    'failed_at',
    'failure_reason',
])]
class AutomationRun extends Model
{
    /** @use HasFactory<AutomationRunFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => AutomationRunStatus::Pending->value,
        'context' => '{}',
        'graph' => '{"nodes":[],"edges":[]}',
    ];

    protected static function booted(): void
    {
        static::creating(function (AutomationRun $run): void {
            $run->uuid ??= (string) Str::uuid();
        });
    }

    /** @return BelongsTo<Automation, $this> */
    public function automation(): BelongsTo
    {
        return $this->belongsTo(Automation::class);
    }

    /** @return BelongsTo<Subscriber, $this> */
    public function subscriber(): BelongsTo
    {
        return $this->belongsTo(Subscriber::class);
    }

    /** @return HasMany<AutomationRunStep, $this> */
    public function steps(): HasMany
    {
        return $this->hasMany(AutomationRunStep::class);
    }

    /** @return HasMany<AutomationEmailDelivery, $this> */
    public function emailDeliveries(): HasMany
    {
        return $this->hasMany(AutomationEmailDelivery::class);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function node(string $id): ?array
    {
        foreach ($this->graph['nodes'] ?? [] as $node) {
            if (($node['id'] ?? null) === $id) {
                return $node;
            }
        }

        return null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function outgoingEdges(string $nodeId, ?string $handle = null): array
    {
        $edges = [];

        foreach ($this->graph['edges'] ?? [] as $edge) {
            if (($edge['source'] ?? null) !== $nodeId) {
                continue;
            }

            if ($handle !== null && ($edge['sourceHandle'] ?? null) !== $handle) {
                continue;
            }

            $edges[] = $edge;
        }

        return $edges;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => AutomationRunStatus::class,
            'graph' => 'array',
            'context' => 'array',
            'scheduled_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
