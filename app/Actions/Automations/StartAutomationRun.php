<?php

namespace App\Actions\Automations;

use App\Enums\AutomationRunStatus;
use App\Jobs\ProcessAutomationRun;
use App\Models\Automation;
use App\Models\AutomationRun;
use App\Models\Subscriber;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

class StartAutomationRun
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function handle(Automation $automation, Subscriber $subscriber, array $context = []): ?AutomationRun
    {
        $open = $automation->runs()
            ->where('subscriber_id', $subscriber->id)
            ->whereIn('status', AutomationRunStatus::open())
            ->exists();

        if ($open) {
            return null;
        }

        $graph = $automation->graph;
        $triggerId = $this->triggerId($graph);

        if ($triggerId === null) {
            return null;
        }

        $targetIds = $this->targetIds($graph, $triggerId);

        try {
            $run = DB::transaction(function () use ($automation, $subscriber, $context, $graph, $triggerId, $targetIds): AutomationRun {
                $run = $automation->runs()->create([
                    'subscriber_id' => $subscriber->id,
                    'status' => $targetIds === [] ? AutomationRunStatus::Completed : AutomationRunStatus::Pending,
                    'graph' => $graph,
                    'context' => $context,
                    'current_node_id' => count($targetIds) === 1 ? $targetIds[0] : null,
                    'started_at' => now(),
                    'completed_at' => $targetIds === [] ? now() : null,
                ]);

                $run->steps()->create([
                    'node_id' => $triggerId,
                    'status' => 'completed',
                    'result' => ['trigger' => $automation->trigger->value],
                    'processed_at' => now(),
                ]);

                foreach ($targetIds as $targetId) {
                    $run->steps()->create([
                        'node_id' => $targetId,
                        'status' => 'pending',
                    ]);
                }

                return $run;
            });
        } catch (UniqueConstraintViolationException) {
            return null;
        }

        foreach ($targetIds as $targetId) {
            DB::afterCommit(fn () => ProcessAutomationRun::dispatch($run->id, $targetId));
        }

        return $run;
    }

    /**
     * @param  array{nodes?: list<array<string, mixed>>, edges?: list<array<string, mixed>>}  $graph
     */
    protected function triggerId(array $graph): ?string
    {
        foreach ($graph['nodes'] ?? [] as $node) {
            if (($node['type'] ?? null) === 'trigger' && is_string($node['id'] ?? null)) {
                return $node['id'];
            }
        }

        return null;
    }

    /**
     * @param  array{nodes?: list<array<string, mixed>>, edges?: list<array<string, mixed>>}  $graph
     * @return list<string>
     */
    protected function targetIds(array $graph, string $sourceId): array
    {
        $targetIds = [];

        foreach ($graph['edges'] ?? [] as $edge) {
            if (($edge['source'] ?? null) === $sourceId && is_string($edge['target'] ?? null)) {
                $targetIds[] = $edge['target'];
            }
        }

        return array_values(array_unique($targetIds));
    }
}
