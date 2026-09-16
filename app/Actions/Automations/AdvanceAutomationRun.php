<?php

namespace App\Actions\Automations;

use App\Actions\Emails\RecordEmailAddressHealth;
use App\Actions\Transactional\RenderTransactionalContent;
use App\Enums\AutomationAction;
use App\Enums\AutomationCondition;
use App\Enums\AutomationDelayUnit;
use App\Enums\AutomationRunStatus;
use App\Enums\EmailDeliveryStatus;
use App\Enums\SubscriberSource;
use App\Enums\SubscriberStatus;
use App\Exceptions\EmailTransportException;
use App\Jobs\ProcessAutomationRun;
use App\Mail\AutomationEmail;
use App\Models\AutomationEmailDelivery;
use App\Models\AutomationRun;
use App\Models\AutomationRunStep;
use App\Models\Tag;
use App\Models\TransactionalEmail;
use App\Services\TeamMailer;
use Carbon\CarbonInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Throwable;

class AdvanceAutomationRun
{
    public function __construct(
        private RenderTransactionalContent $renderer,
        private TeamMailer $teamMailer,
        private RecordEmailAddressHealth $emailHealth,
        private BuildAutomationMergeData $mergeData,
    ) {}

    public function handle(AutomationRun $run, ?string $nodeId = null): void
    {
        $run = AutomationRun::query()
            ->with(['automation.team', 'subscriber.audience', 'subscriber.tags'])
            ->find($run->id);

        if ($run === null || ! $run->status->isOpen()) {
            return;
        }

        if ($run->automation()->withTrashed()->first()?->trashed()) {
            $this->cancel($run, __('The automation was deleted.'));

            return;
        }

        $nodeId ??= $run->current_node_id;

        if ($nodeId === null) {
            $this->synchronizeRun($run);

            return;
        }

        $step = $this->step($run, $nodeId);

        if ($step->status === 'waiting' && $step->scheduled_at?->isFuture()) {
            ProcessAutomationRun::dispatch($run->id, $nodeId)->delay($step->scheduled_at);

            return;
        }

        $wasWaiting = $step->status === 'waiting';

        if (! $this->claim($run, $step)) {
            return;
        }

        $node = $run->node($nodeId);

        if ($node === null) {
            $this->fail($run, $step, __('The current step is missing from the saved graph.'));

            return;
        }

        if (($node['type'] ?? null) === 'delay' && ! $wasWaiting) {
            $delayUntil = $this->delayUntil($node) ?? now()->addMinute();

            $step->update([
                'status' => 'waiting',
                'result' => ['until' => $delayUntil->toISOString()],
                'scheduled_at' => $delayUntil,
                'processed_at' => now(),
            ]);

            $this->synchronizeRun($run);
            ProcessAutomationRun::dispatch($run->id, $nodeId)->delay($delayUntil);

            return;
        }

        try {
            $outcome = $this->execute($run, $nodeId, $node);
        } catch (Throwable $exception) {
            $this->fail($run, $step, $exception->getMessage());

            return;
        }

        $queuedNodeIds = DB::transaction(function () use ($run, $step, $nodeId, $outcome): array {
            $step->update([
                'status' => $outcome['status'],
                'result' => $outcome['result'],
                'scheduled_at' => null,
                'processed_at' => now(),
            ]);

            return $this->enqueueTargets($run, $nodeId, $outcome['handle']);
        });

        $this->synchronizeRun($run);

        foreach ($queuedNodeIds as $queuedNodeId) {
            ProcessAutomationRun::dispatch($run->id, $queuedNodeId);
        }
    }

    /**
     * @param  array<string, mixed>  $node
     * @return array{status: string, result: array<string, mixed>, handle: string|null}
     */
    protected function execute(AutomationRun $run, string $nodeId, array $node): array
    {
        $type = $node['type'] ?? null;
        $data = is_array($node['data'] ?? null) ? $node['data'] : [];

        return match ($type) {
            'delay' => ['status' => 'completed', 'result' => ['waited' => true], 'handle' => null],
            'action' => $this->executeAction($run, $data),
            'condition' => $this->executeCondition($run, $data),
            default => throw new \RuntimeException(__('Unknown automation step.')),
        };
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{status: string, result: array<string, mixed>, handle: string|null}
     */
    protected function executeAction(AutomationRun $run, array $data): array
    {
        $kind = AutomationAction::tryFrom((string) ($data['kind'] ?? ''));

        return match ($kind) {
            AutomationAction::SendEmail => $this->sendEmail($run, $data),
            AutomationAction::AddTag => $this->syncTag($run, $data, add: true),
            AutomationAction::RemoveTag => $this->syncTag($run, $data, add: false),
            default => throw new \RuntimeException(__('Unknown action.')),
        };
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{status: string, result: array<string, mixed>, handle: string|null}
     */
    protected function sendEmail(AutomationRun $run, array $data): array
    {
        $subscriber = $run->subscriber()->firstOrFail();

        if ($subscriber->status !== SubscriberStatus::Subscribed) {
            return [
                'status' => 'skipped',
                'result' => ['reason' => 'unsubscribed'],
                'handle' => null,
            ];
        }

        $uuid = (string) ($data['transactional_email_uuid'] ?? '');
        $email = TransactionalEmail::query()
            ->where('team_id', $run->automation->team_id)
            ->where('uuid', $uuid)
            ->first();

        if ($email === null) {
            throw new \RuntimeException(__('The transactional email is no longer available.'));
        }

        if ($this->emailHealth->isSuppressed($run->automation->team, $subscriber->email)) {
            return [
                'status' => 'skipped',
                'result' => ['reason' => 'suppressed'],
                'handle' => null,
            ];
        }

        $transport = $this->teamMailer->resolve($run->automation->team);
        $delivery = AutomationEmailDelivery::query()->create([
            'team_id' => $run->automation->team_id,
            'automation_run_id' => $run->id,
            'transactional_email_id' => $email->id,
            'subscriber_id' => $subscriber->id,
            'to_address' => $subscriber->email,
            'status' => EmailDeliveryStatus::Sending,
            'provider' => $transport->provider,
            'ses_configuration_set' => $transport->sesConfigurationSet,
            'ses_sns_topic_arn_hash' => $transport->sesSnsTopicArnHash,
            'send_attempted_at' => now(),
        ]);

        // After the delivery exists: the browser copy link is keyed to it.
        $merge = $this->mergeData->handle($subscriber, $run->context, $delivery);

        try {
            $sentMessage = $this->teamMailer->sendResolved(
                $transport,
                $subscriber->email,
                new AutomationEmail(
                    $email,
                    $subscriber,
                    $this->renderer->text($email->subject, $merge),
                    $this->renderer->html($email->html ?? '', $merge),
                    $delivery,
                ),
                $email->resolvedFromAddress(),
            );
        } catch (Throwable $exception) {
            $failureReason = $exception instanceof EmailTransportException
                ? $exception->getMessage()
                : __('Delivery failed.');

            $delivery->update([
                'status' => EmailDeliveryStatus::Failed,
                'failure_reason' => $failureReason,
            ]);
            $this->emailHealth->recordFailure(
                $run->automation->team,
                $subscriber->email,
                $transport->provider,
                $failureReason,
            );

            throw $exception instanceof EmailTransportException
                ? $exception
                : new EmailTransportException($failureReason);
        }

        $delivery->update([
            'status' => EmailDeliveryStatus::Sent,
            'provider_message_id' => $sentMessage?->getMessageId(),
            'sent_at' => now(),
        ]);

        return [
            'status' => 'completed',
            'result' => ['transactional_email_uuid' => $email->uuid],
            'handle' => null,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{status: string, result: array<string, mixed>, handle: string|null}
     */
    protected function syncTag(AutomationRun $run, array $data, bool $add): array
    {
        $uuid = (string) ($data['tag_uuid'] ?? '');
        $tag = Tag::query()
            ->where('team_id', $run->automation->team_id)
            ->where('uuid', $uuid)
            ->first();

        if ($tag === null) {
            throw new \RuntimeException(__('The tag is no longer available.'));
        }

        $subscriber = $run->subscriber()->firstOrFail();

        if ($add) {
            $subscriber->tags()->syncWithoutDetaching([$tag->id]);
        } else {
            $subscriber->tags()->detach($tag->id);
        }

        return [
            'status' => 'completed',
            'result' => ['tag_uuid' => $tag->uuid, 'added' => $add],
            'handle' => null,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{status: string, result: array<string, mixed>, handle: string|null}
     */
    protected function executeCondition(AutomationRun $run, array $data): array
    {
        $kind = AutomationCondition::tryFrom((string) ($data['kind'] ?? ''));
        $subscriber = $run->subscriber()->with('tags')->firstOrFail();

        $matched = match ($kind) {
            AutomationCondition::HasTag => $subscriber->tags->contains(
                fn (Tag $tag): bool => $tag->uuid === ($data['tag_uuid'] ?? null),
            ),
            AutomationCondition::IsSubscribed => $subscriber->status === SubscriberStatus::Subscribed,
            AutomationCondition::Source => $subscriber->source === SubscriberSource::tryFrom(
                (string) ($data['source'] ?? ''),
            ),
            default => throw new \RuntimeException(__('Unknown condition.')),
        };

        return [
            'status' => 'completed',
            'result' => ['matched' => $matched],
            'handle' => $matched ? 'yes' : 'no',
        ];
    }

    /**
     * @param  array<string, mixed>  $node
     */
    protected function delayUntil(?array $node): ?CarbonInterface
    {
        if ($node === null || ($node['type'] ?? null) !== 'delay') {
            return null;
        }

        $data = is_array($node['data'] ?? null) ? $node['data'] : [];
        $amount = max(1, (int) ($data['amount'] ?? 1));
        $unit = AutomationDelayUnit::tryFrom((string) ($data['unit'] ?? '')) ?? AutomationDelayUnit::Minutes;

        return match ($unit) {
            AutomationDelayUnit::Minutes => now()->addMinutes($amount),
            AutomationDelayUnit::Hours => now()->addHours($amount),
            AutomationDelayUnit::Days => now()->addDays($amount),
        };
    }

    protected function step(AutomationRun $run, string $nodeId): AutomationRunStep
    {
        $step = $run->steps()->where('node_id', $nodeId)->first();

        if ($step !== null) {
            return $step;
        }

        try {
            return $run->steps()->create([
                'node_id' => $nodeId,
                'status' => $run->status === AutomationRunStatus::Waiting ? 'waiting' : 'pending',
                'scheduled_at' => $run->scheduled_at,
            ]);
        } catch (UniqueConstraintViolationException) {
            return $run->steps()->where('node_id', $nodeId)->firstOrFail();
        }
    }

    protected function claim(AutomationRun $run, AutomationRunStep $step): bool
    {
        $claimed = AutomationRunStep::query()
            ->whereKey($step->id)
            ->whereIn('status', ['pending', 'waiting'])
            ->where(function ($query): void {
                $query->whereNull('scheduled_at')->orWhere('scheduled_at', '<=', now());
            })
            ->update([
                'status' => 'running',
                'scheduled_at' => null,
            ]) === 1;

        if (! $claimed) {
            return false;
        }

        $step->forceFill([
            'status' => 'running',
            'scheduled_at' => null,
        ])->syncOriginal();

        AutomationRun::query()
            ->whereKey($run->id)
            ->whereIn('status', AutomationRunStatus::open())
            ->update([
                'status' => AutomationRunStatus::Running,
                'started_at' => $run->started_at ?? now(),
                'scheduled_at' => null,
            ]);

        return true;
    }

    /** @return list<string> */
    protected function enqueueTargets(AutomationRun $run, string $nodeId, ?string $handle): array
    {
        $targetIds = collect($run->outgoingEdges($nodeId, $handle))
            ->pluck('target')
            ->filter(fn (mixed $target): bool => is_string($target) && $target !== '')
            ->unique()
            ->values();
        $queuedNodeIds = [];

        foreach ($targetIds as $targetId) {
            try {
                $step = $run->steps()->firstOrCreate(
                    ['node_id' => $targetId],
                    ['status' => 'pending'],
                );
            } catch (UniqueConstraintViolationException) {
                continue;
            }

            if ($step->wasRecentlyCreated) {
                $queuedNodeIds[] = $targetId;
            }
        }

        return $queuedNodeIds;
    }

    protected function synchronizeRun(AutomationRun $run): void
    {
        $run->refresh();

        if (! $run->status->isOpen()) {
            return;
        }

        $openSteps = $run->steps()
            ->whereIn('status', ['pending', 'running', 'waiting'])
            ->orderBy('id')
            ->get(['node_id', 'status', 'scheduled_at']);

        if ($openSteps->isEmpty()) {
            $run->update([
                'status' => AutomationRunStatus::Completed,
                'current_node_id' => null,
                'scheduled_at' => null,
                'completed_at' => now(),
            ]);

            return;
        }

        $status = match (true) {
            $openSteps->contains('status', 'running') => AutomationRunStatus::Running,
            $openSteps->contains('status', 'pending') => AutomationRunStatus::Pending,
            default => AutomationRunStatus::Waiting,
        };
        $scheduledAt = $status === AutomationRunStatus::Waiting
            ? $openSteps->min('scheduled_at')
            : null;

        $run->update([
            'status' => $status,
            'current_node_id' => $openSteps->count() === 1 ? $openSteps->first()->node_id : null,
            'scheduled_at' => $scheduledAt,
        ]);
    }

    protected function fail(AutomationRun $run, AutomationRunStep $step, string $reason): void
    {
        $step->update([
            'status' => 'failed',
            'result' => ['error' => $reason],
            'scheduled_at' => null,
            'processed_at' => now(),
        ]);

        $run->steps()
            ->whereKeyNot($step->id)
            ->whereIn('status', ['pending', 'running', 'waiting'])
            ->update([
                'status' => 'cancelled',
                'scheduled_at' => null,
                'processed_at' => now(),
            ]);

        $run->update([
            'status' => AutomationRunStatus::Failed,
            'current_node_id' => $step->node_id,
            'failure_reason' => $reason,
            'failed_at' => now(),
            'scheduled_at' => null,
        ]);
    }

    protected function cancel(AutomationRun $run, string $reason): void
    {
        $run->steps()
            ->whereIn('status', ['pending', 'running', 'waiting'])
            ->update([
                'status' => 'cancelled',
                'scheduled_at' => null,
                'processed_at' => now(),
            ]);

        $run->update([
            'status' => AutomationRunStatus::Cancelled,
            'current_node_id' => null,
            'failure_reason' => $reason,
            'completed_at' => now(),
            'scheduled_at' => null,
        ]);
    }
}
