<?php

namespace App\Models;

use App\Enums\AutomationStatus;
use App\Enums\AutomationTrigger;
use Database\Factories\AutomationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $uuid
 * @property int $team_id
 * @property string $name
 * @property string|null $description
 * @property AutomationStatus $status
 * @property AutomationTrigger $trigger
 * @property array<string, mixed> $trigger_config
 * @property array{nodes: list<array<string, mixed>>, edges: list<array<string, mixed>>}|array{} $graph
 * @property string $trigger_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Team $team
 * @property-read Collection<int, AutomationRun> $runs
 * @property-read int $enrolled_count
 * @property-read int $running_count
 */
#[Fillable([
    'team_id',
    'name',
    'description',
    'status',
    'trigger',
    'trigger_config',
    'graph',
    'trigger_token',
])]
#[Hidden(['trigger_token'])]
class Automation extends Model
{
    /** @use HasFactory<AutomationFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => AutomationStatus::Draft->value,
        'trigger' => AutomationTrigger::Subscribed->value,
        'trigger_config' => '{}',
        'graph' => '{"nodes":[],"edges":[]}',
    ];

    protected static function booted(): void
    {
        static::creating(function (Automation $automation): void {
            $automation->uuid ??= (string) Str::uuid();
            $automation->trigger_token ??= static::generateTriggerToken();

            if ($automation->graph === ['nodes' => [], 'edges' => []] || $automation->graph === []) {
                $automation->graph = static::defaultGraph();
            }

            $automation->syncTriggerFromGraph();
        });
    }

    /**
     * @return array{nodes: list<array<string, mixed>>, edges: list<array<string, mixed>>}
     */
    public static function defaultGraph(): array
    {
        return [
            'nodes' => [
                [
                    'id' => 'trigger',
                    'type' => 'trigger',
                    'position' => ['x' => 320, 'y' => 40],
                    'deletable' => false,
                    'data' => [
                        'kind' => AutomationTrigger::Subscribed->value,
                        'audience_uuid' => null,
                        'tag_uuid' => null,
                    ],
                ],
            ],
            'edges' => [],
        ];
    }

    public static function generateTriggerToken(): string
    {
        return Str::password(40, symbols: false);
    }

    /** @return BelongsTo<Team, $this> */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /** @return HasMany<AutomationRun, $this> */
    public function runs(): HasMany
    {
        return $this->hasMany(AutomationRun::class);
    }

    /**
     * Names of the team's automations whose saved graph uses the given tag or
     * transactional email, so deleting it would break their steps.
     *
     * @return SupportCollection<int, string>
     */
    public static function namesReferencing(Team $team, string $uuid): SupportCollection
    {
        return static::query()
            ->where('team_id', $team->id)
            ->orderBy('name')
            ->get(['id', 'name', 'graph'])
            ->filter(fn (Automation $automation): bool => $automation->references($uuid))
            ->pluck('name')
            ->values();
    }

    /**
     * Whether any step of the saved graph names the given tag or email.
     */
    public function references(string $uuid): bool
    {
        foreach ($this->graph['nodes'] ?? [] as $node) {
            $data = is_array($node['data'] ?? null) ? $node['data'] : [];

            if (in_array($uuid, [$data['tag_uuid'] ?? null, $data['transactional_email_uuid'] ?? null], true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Why the given tag or email cannot be deleted or unpublished yet, or null.
     */
    public static function referenceBlockReason(Team $team, string $uuid, string $thing): ?string
    {
        $names = static::namesReferencing($team, $uuid);

        if ($names->isEmpty()) {
            return null;
        }

        return trans_choice(
            'The :automations automation uses this :thing. Remove it from that automation first.|The :automations automations use this :thing. Remove it from those automations first.',
            $names->count(),
            ['automations' => $names->join(', ', ' and '), 'thing' => $thing],
        );
    }

    public function isActive(): bool
    {
        return $this->status === AutomationStatus::Active;
    }

    public function isPaused(): bool
    {
        return $this->status === AutomationStatus::Paused;
    }

    /**
     * Copy the trigger node into the denormalized columns used when matching events.
     */
    public function syncTriggerFromGraph(): void
    {
        $nodes = $this->graph['nodes'] ?? [];

        foreach ($nodes as $node) {
            if (($node['type'] ?? null) !== 'trigger') {
                continue;
            }

            $data = is_array($node['data'] ?? null) ? $node['data'] : [];
            $kind = AutomationTrigger::tryFrom((string) ($data['kind'] ?? ''));

            if ($kind === null) {
                return;
            }

            $this->trigger = $kind;
            $this->trigger_config = [
                'audience_uuid' => filled($data['audience_uuid'] ?? null) ? (string) $data['audience_uuid'] : null,
                'tag_uuid' => filled($data['tag_uuid'] ?? null) ? (string) $data['tag_uuid'] : null,
            ];

            return;
        }
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => AutomationStatus::class,
            'trigger' => AutomationTrigger::class,
            'trigger_config' => 'array',
            'graph' => 'array',
            'trigger_token' => 'encrypted',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
