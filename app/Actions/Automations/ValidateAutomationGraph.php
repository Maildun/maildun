<?php

namespace App\Actions\Automations;

use App\Enums\AutomationAction;
use App\Enums\AutomationCondition;
use App\Enums\AutomationDelayUnit;
use App\Enums\AutomationTrigger;
use App\Enums\SubscriberSource;
use App\Enums\TransactionalEmailStatus;
use App\Models\Audience;
use App\Models\Tag;
use App\Models\Team;
use App\Models\TransactionalEmail;

class ValidateAutomationGraph
{
    /**
     * @param  array<string, mixed>  $graph
     * @return list<string>
     */
    public function handle(Team $team, array $graph): array
    {
        $nodes = $graph['nodes'] ?? null;
        $edges = $graph['edges'] ?? null;

        if (! is_array($nodes) || ! is_array($edges)) {
            return [__('The automation graph is invalid.')];
        }

        $nodes = array_values($nodes);
        $edges = array_values($edges);

        $errors = [];
        $ids = [];
        $triggers = [];

        foreach ($nodes as $index => $node) {
            if (! is_array($node) || ! is_string($node['id'] ?? null) || $node['id'] === '') {
                $errors[] = __('Every node needs an id.');

                continue;
            }

            if (isset($ids[$node['id']])) {
                $errors[] = __('Node ids must be unique.');

                continue;
            }

            $ids[$node['id']] = $index;
            $type = $node['type'] ?? null;

            if (! in_array($type, ['trigger', 'action', 'delay', 'condition'], true)) {
                $errors[] = __('Unknown node type.');

                continue;
            }

            $data = is_array($node['data'] ?? null) ? $node['data'] : [];

            $errors = [
                ...$errors,
                ...match ($type) {
                    'trigger' => $this->validateTrigger($team, $data),
                    'action' => $this->validateAction($team, $data),
                    'delay' => $this->validateDelay($data),
                    'condition' => $this->validateCondition($team, $data),
                },
            ];

            if ($type === 'trigger') {
                $triggers[] = $node['id'];
            }
        }

        if (count($triggers) !== 1) {
            $errors[] = __('An automation needs exactly one trigger.');
        }

        $edgeKeys = [];

        foreach ($edges as $edge) {
            if (! is_array($edge)) {
                $errors[] = __('Every connection is invalid.');

                continue;
            }

            $source = $edge['source'] ?? null;
            $target = $edge['target'] ?? null;

            if (! is_string($source) || ! is_string($target) || ! isset($ids[$source], $ids[$target])) {
                $errors[] = __('Every connection must join two nodes on the canvas.');

                continue;
            }

            if ($source === $target) {
                $errors[] = __('A node cannot connect to itself.');
            }

            $handle = is_string($edge['sourceHandle'] ?? null) ? $edge['sourceHandle'] : '';
            $edgeKeys[] = $source.'|'.$handle.'|'.$target;
        }

        if (count($edgeKeys) !== count(array_unique($edgeKeys))) {
            $errors[] = __('Duplicate connections are not allowed.');
        }

        if ($this->hasAmbiguousMerge($nodes, $edges)) {
            $errors[] = __('Parallel branches cannot merge into the same step.');
        }

        $triggerId = $triggers[0] ?? null;

        if ($triggerId !== null) {
            foreach ($edges as $edge) {
                if (is_array($edge) && ($edge['target'] ?? null) === $triggerId) {
                    $errors[] = __('Nothing can connect into the trigger.');
                    break;
                }
            }

            if ($this->hasCycle($nodes, $edges)) {
                $errors[] = __('The automation cannot contain a loop.');
            }

            $reachable = $this->reachableIds($triggerId, $edges);

            foreach ($ids as $id => $index) {
                if (! isset($reachable[$id])) {
                    $errors[] = __('Every step must be reachable from the trigger.');
                    break;
                }
            }

            $actionCount = collect($nodes)
                ->filter(fn (mixed $node): bool => is_array($node) && in_array($node['type'] ?? null, ['action', 'delay', 'condition'], true))
                ->count();

            if ($actionCount === 0) {
                $errors[] = __('Add at least one step after the trigger.');
            }
        }

        return array_values(array_unique($errors));
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<string>
     */
    protected function validateTrigger(Team $team, array $data): array
    {
        $kind = AutomationTrigger::tryFrom((string) ($data['kind'] ?? ''));

        if ($kind === null) {
            return [__('Choose a trigger.')];
        }

        $errors = [];
        $audienceUuid = $data['audience_uuid'] ?? null;

        if (filled($audienceUuid) && ! $this->audienceBelongsToTeam($team, (string) $audienceUuid)) {
            $errors[] = __('The trigger audience is not on this team.');
        }

        if ($kind === AutomationTrigger::Tagged) {
            $tagUuid = $data['tag_uuid'] ?? null;

            if (filled($tagUuid) && ! $this->tagBelongsToTeam($team, (string) $tagUuid)) {
                $errors[] = __('The trigger tag is not on this team.');
            }
        }

        return $errors;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<string>
     */
    protected function validateAction(Team $team, array $data): array
    {
        $kind = AutomationAction::tryFrom((string) ($data['kind'] ?? ''));

        if ($kind === null) {
            return [__('Choose an action.')];
        }

        return match ($kind) {
            AutomationAction::SendEmail => $this->validateSendEmail($team, $data),
            AutomationAction::AddTag, AutomationAction::RemoveTag => $this->validateTagAction($team, $data),
        };
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<string>
     */
    protected function validateSendEmail(Team $team, array $data): array
    {
        $uuid = $data['transactional_email_uuid'] ?? null;

        if (! is_string($uuid) || $uuid === '') {
            return [__('Choose a transactional email to send.')];
        }

        $email = TransactionalEmail::query()
            ->where('team_id', $team->id)
            ->where('uuid', $uuid)
            ->first();

        if ($email === null) {
            return [__('The selected transactional email is not on this team.')];
        }

        if ($email->status !== TransactionalEmailStatus::Published) {
            return [__('Publish the transactional email before using it in an automation.')];
        }

        return [];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<string>
     */
    protected function validateTagAction(Team $team, array $data): array
    {
        $uuid = $data['tag_uuid'] ?? null;

        if (! is_string($uuid) || $uuid === '') {
            return [__('Choose a tag.')];
        }

        if (! $this->tagBelongsToTeam($team, $uuid)) {
            return [__('The selected tag is not on this team.')];
        }

        return [];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<string>
     */
    protected function validateDelay(array $data): array
    {
        $amount = $data['amount'] ?? null;
        $unit = AutomationDelayUnit::tryFrom((string) ($data['unit'] ?? ''));

        if (! is_numeric($amount) || (int) $amount < 1 || (int) $amount > 365) {
            return [__('Wait time must be between 1 and 365.')];
        }

        if ($unit === null) {
            return [__('Choose a wait unit.')];
        }

        return [];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<string>
     */
    protected function validateCondition(Team $team, array $data): array
    {
        $kind = AutomationCondition::tryFrom((string) ($data['kind'] ?? ''));

        if ($kind === null) {
            return [__('Choose a condition.')];
        }

        if ($kind === AutomationCondition::HasTag) {
            $uuid = $data['tag_uuid'] ?? null;

            if (! is_string($uuid) || $uuid === '') {
                return [__('Choose a tag for the condition.')];
            }

            if (! $this->tagBelongsToTeam($team, $uuid)) {
                return [__('The condition tag is not on this team.')];
            }
        }

        if ($kind === AutomationCondition::Source) {
            $source = SubscriberSource::tryFrom((string) ($data['source'] ?? ''));

            if (! in_array($source, [SubscriberSource::Manual, SubscriberSource::Form], true)) {
                return [__('Choose Manual or Form for the source condition.')];
            }
        }

        return [];
    }

    /**
     * @param  list<mixed>  $nodes
     * @param  list<mixed>  $edges
     */
    protected function hasCycle(array $nodes, array $edges): bool
    {
        $adjacency = [];

        foreach ($nodes as $node) {
            if (is_array($node) && is_string($node['id'] ?? null)) {
                $adjacency[$node['id']] = [];
            }
        }

        foreach ($edges as $edge) {
            if (is_array($edge) && isset($adjacency[$edge['source'] ?? ''], $adjacency[$edge['target'] ?? ''])) {
                $adjacency[$edge['source']][] = $edge['target'];
            }
        }

        $state = [];

        $visit = function (string $id) use (&$visit, &$state, $adjacency): bool {
            $state[$id] = 'visiting';

            foreach ($adjacency[$id] ?? [] as $next) {
                $nextState = $state[$next] ?? 'unvisited';

                if ($nextState === 'visiting') {
                    return true;
                }

                if ($nextState === 'unvisited' && $visit($next)) {
                    return true;
                }
            }

            $state[$id] = 'visited';

            return false;
        };

        foreach (array_keys($adjacency) as $id) {
            if (($state[$id] ?? 'unvisited') === 'unvisited' && $visit($id)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<mixed>  $edges
     * @return array<string, true>
     */
    protected function reachableIds(string $start, array $edges): array
    {
        $adjacency = [];

        foreach ($edges as $edge) {
            if (is_array($edge) && is_string($edge['source'] ?? null) && is_string($edge['target'] ?? null)) {
                $adjacency[$edge['source']][] = $edge['target'];
            }
        }

        $stack = [$start];
        $seen = [$start => true];

        while ($stack !== []) {
            $current = array_pop($stack);

            foreach ($adjacency[$current] ?? [] as $next) {
                if (isset($seen[$next])) {
                    continue;
                }

                $seen[$next] = true;
                $stack[] = $next;
            }
        }

        return $seen;
    }

    /**
     * True and False from one condition are mutually exclusive and may safely
     * converge. Any other merge would need join semantics the engine does not
     * currently promise.
     *
     * @param  list<mixed>  $nodes
     * @param  list<mixed>  $edges
     */
    protected function hasAmbiguousMerge(array $nodes, array $edges): bool
    {
        $nodeTypes = [];
        $incoming = [];

        foreach ($nodes as $node) {
            if (is_array($node) && is_string($node['id'] ?? null)) {
                $nodeTypes[$node['id']] = $node['type'] ?? null;
            }
        }

        foreach ($edges as $edge) {
            if (! is_array($edge) || ! is_string($edge['source'] ?? null) || ! is_string($edge['target'] ?? null)) {
                continue;
            }

            $incoming[$edge['target']][] = $edge;
        }

        foreach ($incoming as $targetEdges) {
            if (count($targetEdges) < 2) {
                continue;
            }

            $sources = array_values(array_unique(array_column($targetEdges, 'source')));
            $handles = array_values(array_unique(array_column($targetEdges, 'sourceHandle')));
            sort($handles);

            $isExclusiveConditionMerge = count($sources) === 1
                && ($nodeTypes[$sources[0]] ?? null) === 'condition'
                && $handles === ['no', 'yes']
                && count($targetEdges) === 2;

            if (! $isExclusiveConditionMerge) {
                return true;
            }
        }

        return false;
    }

    protected function audienceBelongsToTeam(Team $team, string $uuid): bool
    {
        return Audience::query()
            ->where('team_id', $team->id)
            ->where('uuid', $uuid)
            ->exists();
    }

    protected function tagBelongsToTeam(Team $team, string $uuid): bool
    {
        return Tag::query()
            ->where('team_id', $team->id)
            ->where('uuid', $uuid)
            ->exists();
    }
}
