<?php

namespace App\Models;

use App\Enums\AutomationAction;
use App\Enums\AutomationCondition;
use App\Enums\AutomationRunStatus;
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
     * For each given tag or transactional email uuid, the names of the team's
     * automations that would break without it: a step of the saved graph uses
     * it, or a run still in flight carries a graph snapshot that does.
     *
     * @param  list<string>  $uuids
     * @return array<string, list<string>>
     */
    public static function namesUsing(Team $team, array $uuids): array
    {
        $automations = static::query()
            ->where('team_id', $team->id)
            ->orderBy('name')
            ->get(['id', 'name', 'graph']);
        $names = $automations->pluck('name', 'id');
        $using = [];

        $collect = function (int $automationId, ?array $graph) use ($uuids, $names, &$using): void {
            foreach (array_intersect(static::stepReferences($graph), $uuids) as $uuid) {
                $using[$uuid][$automationId] = $names[$automationId];
            }
        };

        foreach ($automations as $automation) {
            $collect($automation->id, $automation->graph);
        }

        AutomationRun::query()
            ->whereIn('automation_id', $automations->modelKeys())
            ->whereIn('status', AutomationRunStatus::open())
            ->select(['id', 'automation_id', 'graph'])
            ->chunkById(200, function ($runs) use ($collect): void {
                foreach ($runs as $run) {
                    $collect($run->automation_id, $run->graph);
                }
            });

        return array_map(fn (array $byAutomation): array => array_values($byAutomation), $using);
    }

    /**
     * The tag and transactional email uuids a graph's steps actually use.
     * Keys left behind on a step whose kind changed are ignored.
     *
     * @param  array<string, mixed>|null  $graph
     * @return list<string>
     */
    public static function stepReferences(?array $graph): array
    {
        $uuids = [];

        foreach ($graph['nodes'] ?? [] as $node) {
            $data = is_array($node['data'] ?? null) ? $node['data'] : [];
            $kind = $data['kind'] ?? null;

            $references = match (true) {
                ($node['type'] ?? null) === 'action' && $kind === AutomationAction::SendEmail->value => [
                    $data['transactional_email_uuid'] ?? null,
                    ...array_map(
                        fn (mixed $variant): mixed => is_array($variant) ? ($variant['transactional_email_uuid'] ?? null) : null,
                        is_array($data['language_emails'] ?? null) ? $data['language_emails'] : [],
                    ),
                ],
                ($node['type'] ?? null) === 'action' && in_array($kind, [AutomationAction::AddTag->value, AutomationAction::RemoveTag->value], true),
                ($node['type'] ?? null) === 'condition' && $kind === AutomationCondition::HasTag->value,
                ($node['type'] ?? null) === 'trigger' && $kind === AutomationTrigger::Tagged->value => [$data['tag_uuid'] ?? null],
                default => [],
            };

            foreach ($references as $uuid) {
                if (is_string($uuid) && $uuid !== '') {
                    $uuids[] = $uuid;
                }
            }
        }

        return array_values(array_unique($uuids));
    }

    /**
     * Why the given tag or email cannot be deleted or unpublished yet, or null.
     *
     * @param  list<string>  $names
     */
    public static function referenceBlockReason(array $names, string $thing): ?string
    {
        if ($names === []) {
            return null;
        }

        return trans_choice(
            'The :automations automation uses this :thing. Remove it from that automation first.|The :automations automations use this :thing. Remove it from those automations first.',
            count($names),
            ['automations' => collect($names)->join(', ', ' and '), 'thing' => $thing],
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
