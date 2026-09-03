<?php

namespace App\Http\Controllers;

use App\Actions\Automations\ValidateAutomationGraph;
use App\Enums\AutomationAction;
use App\Enums\AutomationCondition;
use App\Enums\AutomationDelayUnit;
use App\Enums\AutomationRunStatus;
use App\Enums\AutomationStatus;
use App\Enums\AutomationTrigger;
use App\Enums\SubscriberSource;
use App\Enums\TransactionalEmailStatus;
use App\Http\Requests\StoreAutomationRequest;
use App\Http\Requests\UpdateAutomationRequest;
use App\Models\Automation;
use App\Models\AutomationRun;
use App\Models\AutomationRunStep;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AutomationController extends Controller
{
    public function index(Team $currentTeam): Response
    {
        Gate::authorize('viewAny', [Automation::class, $currentTeam]);

        $automations = $currentTeam->automations()
            ->withCount([
                'runs as enrolled_count',
                'runs as running_count' => fn ($query) => $query->whereIn('status', AutomationRunStatus::open()),
            ])
            ->orderByDesc('updated_at')
            ->paginate(15)
            ->through(fn (Automation $automation): array => [
                'uuid' => $automation->uuid,
                'name' => $automation->name,
                'description' => $automation->description,
                'status' => $automation->status->value,
                'trigger' => $automation->trigger->value,
                'trigger_label' => $automation->trigger->label(),
                'enrolled_count' => $automation->enrolled_count,
                'running_count' => $automation->running_count,
                'updated_at' => $automation->updated_at?->toISOString(),
            ]);

        return Inertia::render('automations/index', [
            'automations' => $automations,
            'canManage' => Gate::allows('create', [Automation::class, $currentTeam]),
        ]);
    }

    public function store(StoreAutomationRequest $request, Team $currentTeam): RedirectResponse
    {
        $automation = $currentTeam->automations()->create([
            'name' => $request->string('name')->value(),
            'description' => $request->input('description'),
            'graph' => Automation::defaultGraph(),
            'trigger_token' => Automation::generateTriggerToken(),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Automation created.')]);

        return to_route('automations.edit', ['current_team' => $currentTeam, 'automation' => $automation]);
    }

    public function edit(Team $currentTeam, Automation $automation): Response
    {
        Gate::authorize('view', $automation);

        return Inertia::render('automations/edit', [
            'automation' => [
                'uuid' => $automation->uuid,
                'name' => $automation->name,
                'description' => $automation->description,
                'status' => $automation->status->value,
                'trigger' => $automation->trigger->value,
                'graph' => $automation->graph,
                'trigger_token' => Gate::allows('update', $automation) ? $automation->trigger_token : null,
                'updated_at' => $automation->updated_at?->toISOString(),
            ],
            'audiences' => $currentTeam->audiences()
                ->orderBy('name')
                ->get(['uuid', 'name'])
                ->map(fn ($audience) => ['uuid' => $audience->uuid, 'name' => $audience->name])
                ->values()
                ->all(),
            'tags' => $currentTeam->tags()
                ->orderBy('name')
                ->get(['uuid', 'name', 'color'])
                ->map(fn ($tag) => ['uuid' => $tag->uuid, 'name' => $tag->name, 'color' => $tag->color])
                ->values()
                ->all(),
            'transactionalEmails' => $currentTeam->transactionalEmails()
                ->where('status', TransactionalEmailStatus::Published)
                ->orderBy('name')
                ->get(['uuid', 'name', 'subject'])
                ->map(fn ($email) => [
                    'uuid' => $email->uuid,
                    'name' => $email->name,
                    'subject' => $email->subject,
                ])
                ->values()
                ->all(),
            'catalog' => [
                'triggers' => collect(AutomationTrigger::cases())
                    ->map(fn (AutomationTrigger $trigger) => [
                        'value' => $trigger->value,
                        'label' => $trigger->label(),
                    ])
                    ->all(),
                'actions' => collect(AutomationAction::cases())
                    ->map(fn (AutomationAction $action) => [
                        'value' => $action->value,
                        'label' => $action->label(),
                    ])
                    ->all(),
                'conditions' => collect(AutomationCondition::cases())
                    ->map(fn (AutomationCondition $condition) => [
                        'value' => $condition->value,
                        'label' => $condition->label(),
                    ])
                    ->all(),
                'sources' => collect([SubscriberSource::Manual, SubscriberSource::Form])
                    ->map(fn (SubscriberSource $source) => [
                        'value' => $source->value,
                        'label' => $source->label(),
                    ])
                    ->all(),
                'delayUnits' => collect(AutomationDelayUnit::cases())
                    ->map(fn (AutomationDelayUnit $unit) => [
                        'value' => $unit->value,
                        'label' => $unit->label(),
                    ])
                    ->all(),
            ],
            'canManage' => Gate::allows('update', $automation),
        ]);
    }

    public function update(
        UpdateAutomationRequest $request,
        Team $currentTeam,
        Automation $automation,
        ValidateAutomationGraph $validator,
    ): RedirectResponse {
        $graph = $request->input('graph');

        if ($automation->status === AutomationStatus::Active && is_array($graph)) {
            $errors = $validator->handle($currentTeam, $graph);

            if ($errors !== []) {
                throw ValidationException::withMessages(['graph' => $errors]);
            }
        }

        $automation->fill([
            'name' => $request->string('name')->value(),
            'description' => $request->input('description'),
            'graph' => $graph,
        ]);
        $automation->syncTriggerFromGraph();
        $automation->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Automation saved.')]);

        return back();
    }

    public function activate(
        Team $currentTeam,
        Automation $automation,
        ValidateAutomationGraph $validator,
    ): RedirectResponse {
        Gate::authorize('activate', $automation);

        $errors = $validator->handle($currentTeam, $automation->graph);

        if ($errors !== []) {
            throw ValidationException::withMessages(['graph' => $errors]);
        }

        $automation->syncTriggerFromGraph();
        $automation->status = AutomationStatus::Active;
        $automation->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Automation activated.')]);

        return back();
    }

    public function pause(Team $currentTeam, Automation $automation): RedirectResponse
    {
        Gate::authorize('pause', $automation);

        if ($automation->status !== AutomationStatus::Active) {
            throw ValidationException::withMessages([
                'status' => __('Only an active automation can be paused.'),
            ]);
        }

        $automation->update(['status' => AutomationStatus::Paused]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Automation paused.')]);

        return back();
    }

    public function regenerateToken(Team $currentTeam, Automation $automation): RedirectResponse
    {
        Gate::authorize('update', $automation);

        $automation->update(['trigger_token' => Automation::generateTriggerToken()]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('API token regenerated.')]);

        return back();
    }

    /**
     * The recorded runs for one automation: who entered, where they are, and
     * which steps they walked. This is the only place a run is visible, so it
     * reads the step trail rather than replaying the graph.
     */
    public function activity(Request $request, Team $currentTeam, Automation $automation): Response
    {
        Gate::authorize('view', $automation);

        $filters = $this->activityFilters($request);

        $runs = $automation->runs()
            ->with(['subscriber:id,uuid,email,first_name,last_name,audience_id', 'subscriber.audience:id,uuid', 'steps'])
            ->when($filters['q'] !== '', function ($query) use ($filters): void {
                $search = '%'.addcslashes($filters['q'], '%_\\').'%';
                $query->whereHas('subscriber', fn ($subscriber) => $subscriber->where('email', 'like', $search));
            })
            ->when($filters['status'] !== '', fn ($query) => $query->where('status', $filters['status']))
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        $names = $this->stepNames($currentTeam, $runs->getCollection());

        $runs->through(fn (AutomationRun $run): array => $this->runProps($run, $names));

        $counts = $automation->runs()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return Inertia::render('automations/activity', [
            'automation' => [
                'uuid' => $automation->uuid,
                'name' => $automation->name,
                'status' => $automation->status->value,
                'trigger_label' => $automation->trigger->label(),
            ],
            'runs' => $runs,
            'filters' => $filters,
            'summary' => [
                'enrolled' => (int) $counts->sum(),
                'in_flight' => (int) collect(AutomationRunStatus::open())
                    ->sum(fn (AutomationRunStatus $status): int => (int) $counts->get($status->value, 0)),
                'completed' => (int) $counts->get(AutomationRunStatus::Completed->value, 0),
                'failed' => (int) $counts->get(AutomationRunStatus::Failed->value, 0),
            ],
            'statuses' => collect(AutomationRunStatus::cases())
                ->map(fn (AutomationRunStatus $status): array => [
                    'value' => $status->value,
                    'label' => $status->label(),
                ])
                ->all(),
            'canManage' => Gate::allows('update', $automation),
        ]);
    }

    /**
     * @return array{q: string, status: string}
     */
    private function activityFilters(Request $request): array
    {
        $status = $request->string('status')->toString();

        return [
            'q' => $request->string('q')->trim()->toString(),
            'status' => AutomationRunStatus::tryFrom($status)->value ?? '',
        ];
    }

    /**
     * Tag and email names for every uuid the page's steps refer to, resolved in
     * one pass so a step detail does not query per row.
     *
     * @param  Collection<int, AutomationRun>  $runs
     * @return array<string, string>
     */
    private function stepNames(Team $team, Collection $runs): array
    {
        $uuids = [];

        foreach ($runs as $run) {
            foreach ($run->steps as $step) {
                $result = is_array($step->result) ? $step->result : [];

                foreach (['tag_uuid', 'transactional_email_uuid'] as $key) {
                    if (is_string($result[$key] ?? null)) {
                        $uuids[] = $result[$key];
                    }
                }
            }
        }

        if ($uuids === []) {
            return [];
        }

        $uuids = array_values(array_unique($uuids));

        return [
            ...$team->tags()->whereIn('uuid', $uuids)->pluck('name', 'uuid')->all(),
            ...$team->transactionalEmails()->whereIn('uuid', $uuids)->pluck('name', 'uuid')->all(),
        ];
    }

    /**
     * @param  array<string, string>  $names
     * @return array<string, mixed>
     */
    private function runProps(AutomationRun $run, array $names): array
    {
        $subscriber = $run->subscriber;
        $openSteps = $run->steps->whereIn('status', ['pending', 'running', 'waiting']);
        $currentStep = match (true) {
            $openSteps->count() > 1 => __(':count active branches', ['count' => $openSteps->count()]),
            $openSteps->count() === 1 => $this->nodeLabel($run, $openSteps->first()->node_id),
            default => null,
        };

        return [
            'uuid' => $run->uuid,
            'status' => $run->status->value,
            'status_label' => $run->status->label(),
            'subscriber' => [
                'uuid' => $subscriber->uuid,
                'audience_uuid' => $subscriber->audience->uuid,
                'email' => $subscriber->email,
                'name' => trim(($subscriber->first_name ?? '').' '.($subscriber->last_name ?? '')) ?: null,
            ],
            'current_step' => $currentStep,
            'failure_reason' => $run->failure_reason,
            'started_at' => $run->started_at?->toISOString(),
            'scheduled_at' => $run->scheduled_at?->toISOString(),
            'completed_at' => $run->completed_at?->toISOString(),
            'failed_at' => $run->failed_at?->toISOString(),
            'steps' => $run->steps
                ->sortBy('id')
                ->map(function (AutomationRunStep $step) use ($run, $names): array {
                    $label = $this->nodeLabel($run, $step->node_id);
                    $detail = $this->stepDetail($step, $names);

                    return [
                        'uuid' => $step->uuid,
                        'label' => $label,
                        // A trigger step's result names the same trigger the
                        // label already shows; do not print it twice.
                        'detail' => $detail === $label ? null : $detail,
                        'status' => $step->status,
                        'processed_at' => $step->processed_at?->toISOString(),
                    ];
                })
                ->values()
                ->all(),
        ];
    }

    /**
     * Steps name a node in the run's own graph snapshot, so a node the editor
     * has since deleted still reads as something rather than a raw id.
     */
    private function nodeLabel(AutomationRun $run, string $nodeId): string
    {
        $node = $run->node($nodeId);

        if ($node === null) {
            return __('Removed step');
        }

        $data = is_array($node['data'] ?? null) ? $node['data'] : [];
        $kind = (string) ($data['kind'] ?? '');

        return match ($node['type'] ?? null) {
            'trigger' => AutomationTrigger::tryFrom($kind)?->label() ?? __('Trigger'),
            'action' => AutomationAction::tryFrom($kind)?->label() ?? __('Action'),
            'condition' => AutomationCondition::tryFrom($kind)?->label() ?? __('Condition'),
            'delay' => __('Wait :amount :unit', [
                'amount' => (int) ($data['amount'] ?? 0),
                'unit' => Str::lower(AutomationDelayUnit::tryFrom((string) ($data['unit'] ?? ''))?->label() ?? ''),
            ]),
            default => __('Step'),
        };
    }

    /**
     * @param  array<string, string>  $names
     */
    private function stepDetail(AutomationRunStep $step, array $names): ?string
    {
        $result = is_array($step->result) ? $step->result : [];

        if (isset($result['matched'])) {
            return $result['matched'] === true ? __('Took the True branch') : __('Took the False branch');
        }

        if (is_string($result['reason'] ?? null)) {
            return match ($result['reason']) {
                'unsubscribed' => __('Skipped: the recipient had unsubscribed'),
                'already_attempted' => __('Skipped: this step was already attempted'),
                default => $result['reason'],
            };
        }

        if (is_string($result['error'] ?? null)) {
            return $result['error'];
        }

        foreach (['transactional_email_uuid', 'tag_uuid'] as $key) {
            $uuid = $result[$key] ?? null;

            if (is_string($uuid)) {
                return $names[$uuid] ?? __('No longer available');
            }
        }

        if (is_string($result['trigger'] ?? null)) {
            return AutomationTrigger::tryFrom($result['trigger'])?->label();
        }

        return null;
    }

    public function destroy(Team $currentTeam, Automation $automation): RedirectResponse
    {
        Gate::authorize('delete', $automation);

        $openRuns = $automation->runs()
            ->whereIn('status', AutomationRunStatus::open())
            ->get();

        AutomationRunStep::query()
            ->whereIn('automation_run_id', $openRuns->modelKeys())
            ->whereIn('status', ['pending', 'running', 'waiting'])
            ->update([
                'status' => 'cancelled',
                'scheduled_at' => null,
                'processed_at' => now(),
            ]);

        AutomationRun::query()
            ->whereKey($openRuns->modelKeys())
            ->update([
                'status' => AutomationRunStatus::Cancelled->value,
                'current_node_id' => null,
                'failure_reason' => __('The automation was deleted.'),
                'completed_at' => now(),
                'scheduled_at' => null,
            ]);

        $automation->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Automation deleted.')]);

        return to_route('automations.index', ['current_team' => $currentTeam]);
    }
}
