<?php

namespace App\Actions\Automations;

use App\Actions\Emails\BuildTrackedEmailHtml;
use App\Actions\Emails\ConvertHtmlToText;
use App\Actions\Emails\InsertPreheader;
use App\Actions\Emails\RecordEmailAddressHealth;
use App\Actions\Transactional\RenderTransactionalContent;
use App\Enums\AutomationAction;
use App\Enums\AutomationCondition;
use App\Enums\AutomationDelayUnit;
use App\Enums\AutomationRunStatus;
use App\Enums\AutomationStatus;
use App\Enums\EmailDeliveryStatus;
use App\Enums\SubscriberLanguage;
use App\Enums\SubscriberSource;
use App\Jobs\ProcessAutomationRun;
use App\Jobs\SendAutomationEmailDelivery;
use App\Mail\AutomationEmail;
use App\Models\AutomationEmailDelivery;
use App\Models\AutomationRun;
use App\Models\AutomationRunStep;
use App\Models\Subscriber;
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
        private DispatchTaggedTriggers $dispatchTaggedTriggers,
        private InsertPreheader $insertPreheader,
        private BuildTrackedEmailHtml $trackedHtml,
        private ConvertHtmlToText $htmlToText,
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

        // Pausing holds every open branch where it is. The step stays pending
        // or waiting, and activating the automation dispatches it again.
        if ($run->automation->status !== AutomationStatus::Active) {
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
            $outcome = $this->execute($run, $step, $node);
        } catch (Throwable $exception) {
            $this->fail($run, $step, $exception->getMessage());

            return;
        }

        // A send step stays running until SendAutomationEmailDelivery reports
        // back, so automations:resume never dispatches it twice. Dispatch last:
        // on the sync queue the job runs inline and completes the step itself.
        if (isset($outcome['deliveryId'])) {
            SendAutomationEmailDelivery::dispatch($outcome['deliveryId']);

            return;
        }

        $this->completeStep($run, $step, $outcome);
    }

    /**
     * Record a claimed step's outcome and hand its targets to the queue.
     *
     * Only a step that is still running is written: a sibling failure, the
     * stale-worker sweep or deleting the automation may have closed it while
     * its work was in flight, and that outcome must stand.
     *
     * @param  array{status: string, result: array<string, mixed>, handle: string|null}  $outcome
     */
    public function completeStep(AutomationRun $run, AutomationRunStep $step, array $outcome): void
    {
        $queuedNodeIds = DB::transaction(function () use ($run, $step, $outcome): array {
            $openStep = AutomationRunStep::query()
                ->whereKey($step->id)
                ->where('status', 'running')
                ->lockForUpdate()
                ->first();

            if ($openStep === null) {
                return [];
            }

            $openStep->update([
                'status' => $outcome['status'],
                'result' => $outcome['result'],
                'scheduled_at' => null,
                'processed_at' => now(),
            ]);

            return $this->enqueueTargets($run, $openStep->node_id, $outcome['handle']);
        });

        $this->synchronizeRun($run);

        foreach ($queuedNodeIds as $queuedNodeId) {
            ProcessAutomationRun::dispatch($run->id, $queuedNodeId);
        }
    }

    /**
     * Hand a claimed step back to pending without running it, as pausing the
     * automation does with an email still waiting for its send job. Activating
     * the automation dispatches it again.
     */
    public function releaseStep(AutomationRun $run, AutomationRunStep $step): void
    {
        $released = AutomationRunStep::query()
            ->whereKey($step->id)
            ->where('status', 'running')
            ->update([
                'status' => 'pending',
                'scheduled_at' => null,
            ]) === 1;

        if (! $released) {
            return;
        }

        $step->forceFill([
            'status' => 'pending',
            'scheduled_at' => null,
        ])->syncOriginal();

        $this->synchronizeRun($run);
    }

    /**
     * The email a send step may still deliver, or the outcome that skips it.
     *
     * Checked when the step queues its delivery and again right before the
     * hand-off to the transport, because the send job can wait in between.
     * An opt-out skips the step; an email that is gone or unpublished throws
     * and fails the run.
     *
     * @return TransactionalEmail|array{status: string, result: array<string, mixed>, handle: null}
     */
    public function sendableEmail(AutomationRun $run, Subscriber $subscriber, ?TransactionalEmail $email): TransactionalEmail|array
    {
        if (! $subscriber->isOptedIn()) {
            return [
                'status' => 'skipped',
                'result' => ['reason' => $subscriber->isPendingConfirmation() ? 'unconfirmed' : 'unsubscribed'],
                'handle' => null,
            ];
        }

        if ($email === null) {
            throw new \RuntimeException(__('The transactional email is no longer available.'));
        }

        // ValidateAutomationGraph only checks this on save and activation; the
        // email can be unpublished while runs are already heading for it.
        if (! $email->isPublished()) {
            throw new \RuntimeException(__('The transactional email is no longer published.'));
        }

        if ($this->emailHealth->isSuppressed($run->automation->team, $subscriber->email)) {
            return [
                'status' => 'skipped',
                'result' => ['reason' => 'suppressed'],
                'handle' => null,
            ];
        }

        return $email;
    }

    /**
     * Render the message for a queued delivery. The delivery must already
     * exist, because the browser copy link is keyed to it.
     */
    public function buildMessage(
        AutomationRun $run,
        AutomationEmailDelivery $delivery,
        TransactionalEmail $email,
        Subscriber $subscriber,
    ): AutomationEmail {
        $merge = $this->mergeData->handle($subscriber, $run->context, $delivery);
        $html = $this->insertPreheader->handle(
            $this->renderer->html($email->html ?? '', $merge),
            $this->renderer->text($email->preheader ?? '', $merge),
        );

        // Automation mail is marketing mail, so it gets the campaign's visible
        // opt-out unless the author already placed {{ unsubscribe_url }}.
        if (! $this->trackedHtml->authorPlacedUnsubscribe($email->html ?? '')) {
            $html = $this->trackedHtml->withUnsubscribeFooter($html, (string) $merge['unsubscribe_url']);
        }

        return new AutomationEmail(
            $email,
            $subscriber,
            $this->renderer->text($email->subject, $merge),
            $html,
            $delivery,
            $this->htmlToText->handle($html),
        );
    }

    /**
     * @param  array<string, mixed>  $node
     * @return array{status: string, result: array<string, mixed>, handle: string|null, deliveryId?: int}
     */
    protected function execute(AutomationRun $run, AutomationRunStep $step, array $node): array
    {
        $type = $node['type'] ?? null;
        $data = is_array($node['data'] ?? null) ? $node['data'] : [];

        return match ($type) {
            'delay' => ['status' => 'completed', 'result' => ['waited' => true], 'handle' => null],
            'action' => $this->executeAction($run, $step, $data),
            'condition' => $this->executeCondition($run, $data),
            default => throw new \RuntimeException(__('Unknown automation step.')),
        };
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{status: string, result: array<string, mixed>, handle: string|null, deliveryId?: int}
     */
    protected function executeAction(AutomationRun $run, AutomationRunStep $step, array $data): array
    {
        $kind = AutomationAction::tryFrom((string) ($data['kind'] ?? ''));

        return match ($kind) {
            AutomationAction::SendEmail => $this->queueEmail($run, $step, $data),
            AutomationAction::AddTag => $this->syncTag($run, $data, add: true),
            AutomationAction::RemoveTag => $this->syncTag($run, $data, add: false),
            AutomationAction::SetLanguage => $this->setLanguage($run, $data),
            default => throw new \RuntimeException(__('Unknown action.')),
        };
    }

    /**
     * Write the step's delivery as Queued for SendAutomationEmailDelivery,
     * which owns the hand-off to the transport and its retries.
     *
     * @param  array<string, mixed>  $data
     * @return array{status: string, result: array<string, mixed>, handle: string|null, deliveryId?: int}
     */
    protected function queueEmail(AutomationRun $run, AutomationRunStep $step, array $data): array
    {
        $subscriber = $run->subscriber()->firstOrFail();
        $email = TransactionalEmail::query()
            ->where('team_id', $run->automation->team_id)
            ->where('uuid', $this->emailUuidFor($subscriber->language, $data))
            ->first();

        $sendable = $this->sendableEmail($run, $subscriber, $email);

        if (is_array($sendable)) {
            return $sendable;
        }

        // A workspace with no usable connection fails the run here, as before.
        $transport = $this->teamMailer->resolve($run->automation->team);
        $delivery = AutomationEmailDelivery::query()->create([
            'team_id' => $run->automation->team_id,
            'automation_run_id' => $run->id,
            'automation_run_step_id' => $step->id,
            'transactional_email_id' => $sendable->id,
            'subscriber_id' => $subscriber->id,
            'to_address' => $subscriber->email,
            'status' => EmailDeliveryStatus::Queued,
            'provider' => $transport->provider,
            'ses_configuration_set' => $transport->sesConfigurationSet,
            'ses_sns_topic_arn_hash' => $transport->sesSnsTopicArnHash,
        ]);

        return [
            'status' => 'running',
            'result' => [],
            'handle' => null,
            'deliveryId' => $delivery->id,
        ];
    }

    /**
     * The language version matching the subscriber, or the step's default email
     * for everyone else.
     *
     * @param  array<string, mixed>  $data
     */
    protected function emailUuidFor(?SubscriberLanguage $language, array $data): string
    {
        $variants = is_array($data['language_emails'] ?? null) ? $data['language_emails'] : [];

        foreach ($variants as $variant) {
            if ($language !== null
                && is_array($variant)
                && ($variant['language'] ?? null) === $language->value
                && is_string($variant['transactional_email_uuid'] ?? null)
            ) {
                return $variant['transactional_email_uuid'];
            }
        }

        return (string) ($data['transactional_email_uuid'] ?? '');
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{status: string, result: array<string, mixed>, handle: string|null}
     */
    protected function setLanguage(AutomationRun $run, array $data): array
    {
        $language = SubscriberLanguage::tryFrom((string) ($data['language'] ?? ''));

        if ($language === null) {
            throw new \RuntimeException(__('Choose a language to set.'));
        }

        $subscriber = $run->subscriber()->firstOrFail();

        if ($subscriber->language !== $language) {
            $subscriber->update(['language' => $language]);
        }

        return [
            'status' => 'completed',
            'result' => ['language' => $language->value],
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
            $attached = $subscriber->tags()->syncWithoutDetaching([$tag->id])['attached'];

            if ($attached !== [] && $subscriber->contact !== null) {
                $this->dispatchTaggedTriggers->handle($subscriber->contact, [$tag->uuid], $run->automation);
            }
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
            AutomationCondition::IsSubscribed => $subscriber->isOptedIn(),
            AutomationCondition::Source => $subscriber->source === SubscriberSource::tryFrom(
                (string) ($data['source'] ?? ''),
            ),
            AutomationCondition::Language => $subscriber->language !== null
                && $subscriber->language === SubscriberLanguage::tryFrom((string) ($data['language'] ?? '')),
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
