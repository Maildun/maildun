<?php

namespace App\Jobs;

use App\Actions\Automations\AdvanceAutomationRun;
use App\Actions\Emails\RecordEmailAddressHealth;
use App\Concerns\ThrottlesEmailDelivery;
use App\Enums\AutomationStatus;
use App\Enums\EmailDeliveryStatus;
use App\Enums\EmailFailureCode;
use App\Exceptions\EmailAddressSuppressedException;
use App\Exceptions\EmailTransportException;
use App\Models\AutomationEmailDelivery;
use App\Models\AutomationRunStep;
use App\Services\TeamMailer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use PDOException;
use RuntimeException;
use Throwable;

/**
 * Hands one automation email to the transport.
 *
 * The send step stays running while this job owns its delivery: a transport
 * error hands the delivery back so the queue retries it on its backoff, and
 * only a delivery that runs out of retries fails the run. It stays on the
 * default queue, never the campaign or transactional ones, and waits for a
 * send slot like every other send.
 */
class SendAutomationEmailDelivery implements ShouldQueue
{
    use Queueable, ThrottlesEmailDelivery;

    public int $tries = 3;

    public int $timeout = 45;

    /** @var list<int> */
    public array $backoff = [30, 120, 300];

    public function __construct(public int $deliveryId) {}

    /**
     * Execute the job.
     */
    public function handle(TeamMailer $teamMailer, AdvanceAutomationRun $advance): void
    {
        $delivery = AutomationEmailDelivery::query()->with([
            'team',
            'step',
            'transactionalEmail',
            'run.subscriber',
            'run.automation' => fn ($query) => $query->withTrashed(),
            'run.automation.team',
        ])->find($this->deliveryId);

        if ($delivery === null || $delivery->step === null || $delivery->run === null) {
            return;
        }

        $run = $delivery->run;
        $step = $delivery->step;

        // The email went out but the job stopped before recording its step,
        // so the retry only finishes that.
        if ($delivery->status === EmailDeliveryStatus::Sent && $step->status === 'running') {
            $advance->completeStep($run, $step, [
                'status' => 'completed',
                'result' => ['transactional_email_uuid' => $delivery->transactionalEmail?->uuid],
                'handle' => null,
            ]);

            return;
        }

        if ($delivery->status !== EmailDeliveryStatus::Queued) {
            return;
        }

        if ($step->status !== 'running' || ! $run->status->isOpen()) {
            $this->close($delivery, EmailDeliveryStatus::Cancelled, __('The automation run ended before this email was sent.'));

            return;
        }

        if ($run->automation->trashed() || $run->automation->status !== AutomationStatus::Active) {
            if ($this->close($delivery, EmailDeliveryStatus::Cancelled, __('The automation was paused before this email was sent.'))) {
                $advance->releaseStep($run, $step);
            }

            return;
        }

        try {
            $sendable = $advance->sendableEmail($run, $run->subscriber, $delivery->transactionalEmail);
        } catch (PDOException $exception) {
            // A failed query is transient: let the queue retry it.
            throw $exception;
        } catch (RuntimeException $exception) {
            // A missing or unpublished email will not come back on a retry.
            if ($this->close($delivery, EmailDeliveryStatus::Failed, $exception->getMessage())) {
                (new ProcessAutomationRun($run->id, $step->node_id))->failed($exception);
            }

            return;
        }

        if (is_array($sendable)) {
            $suppressed = ($sendable['result']['reason'] ?? null) === 'suppressed';

            $closed = $this->close(
                $delivery,
                $suppressed ? EmailDeliveryStatus::Rejected : EmailDeliveryStatus::Cancelled,
                $suppressed
                    ? __(EmailAddressSuppressedException::MESSAGE)
                    : __('The subscriber unsubscribed before this email was sent.'),
            );

            if ($closed) {
                $advance->completeStep($run, $step, $sendable);
            }

            return;
        }

        // Render before claiming, so a render error never leaves the delivery
        // in Sending.
        $message = $advance->buildMessage($run, $delivery, $sendable, $run->subscriber);

        if (! $this->claim($delivery)) {
            return;
        }

        try {
            $transport = $teamMailer->resolve($delivery->team);

            $delivery->forceFill([
                'provider' => $transport->provider,
                'ses_configuration_set' => $transport->sesConfigurationSet,
                'ses_sns_topic_arn_hash' => $transport->sesSnsTopicArnHash,
            ])->save();

            $sentMessage = $teamMailer->sendResolved(
                $transport,
                $delivery->to_address,
                $message,
                $sendable->resolvedFromAddress(),
            );
        } catch (Throwable $exception) {
            // Nothing reached the recipient, so the queue retries on its backoff.
            $this->releaseClaim($delivery);

            throw $exception;
        }

        AutomationEmailDelivery::query()
            ->whereKey($delivery->id)
            ->where('status', EmailDeliveryStatus::Sending)
            ->update([
                'status' => EmailDeliveryStatus::Sent,
                'provider_message_id' => $sentMessage?->getMessageId(),
                'sent_at' => now(),
            ]);

        $advance->completeStep($run, $step, [
            'status' => 'completed',
            'result' => ['transactional_email_uuid' => $sendable->uuid],
            'handle' => null,
        ]);
    }

    /**
     * Only a delivery that never finished can fail: a late failure must not
     * overwrite one that was sent. The address is charged only when the
     * provider refused the message; a timeout or a workspace connection
     * problem says nothing about it. Only the sanitized transport message is
     * stored, because other exceptions can echo provider credentials.
     */
    public function failed(?Throwable $exception): void
    {
        $failureReason = $exception instanceof EmailTransportException
            ? $exception->getMessage()
            : __('Delivery failed.');

        $failed = AutomationEmailDelivery::query()
            ->whereKey($this->deliveryId)
            ->whereIn('status', [EmailDeliveryStatus::Queued, EmailDeliveryStatus::Sending])
            ->update([
                'status' => EmailDeliveryStatus::Failed,
                'failure_reason' => $failureReason,
            ]);

        if ($failed === 0) {
            return;
        }

        $delivery = AutomationEmailDelivery::query()->with(['team', 'step'])->find($this->deliveryId);

        if ($delivery === null) {
            return;
        }

        if ($exception instanceof EmailTransportException && $exception->failureCode === EmailFailureCode::ProviderRefused) {
            app(RecordEmailAddressHealth::class)->recordFailure(
                $delivery->team,
                $delivery->to_address,
                $delivery->provider,
                $failureReason,
            );
        }

        if ($delivery->step !== null) {
            (new ProcessAutomationRun($delivery->automation_run_id, $delivery->step->node_id))
                ->failed(new RuntimeException($failureReason));
        }
    }

    /** @return list<string> */
    public function tags(): array
    {
        return ['automation-delivery:'.$this->deliveryId];
    }

    /**
     * Take ownership of the delivery immediately before handing it to the
     * transport. Its step must still be running, which also means its run is
     * open, so a run that ended or a duplicate job sends nothing.
     */
    private function claim(AutomationEmailDelivery $delivery): bool
    {
        return AutomationEmailDelivery::query()
            ->whereKey($delivery->id)
            ->where('status', EmailDeliveryStatus::Queued)
            ->whereNull('send_attempted_at')
            ->whereIn('automation_run_step_id', AutomationRunStep::query()->where('status', 'running')->select('id'))
            ->update([
                'status' => EmailDeliveryStatus::Sending,
                'send_attempted_at' => now(),
                'failure_reason' => null,
            ]) === 1;
    }

    /**
     * Hand the delivery back when the transport refused it. Touching the row
     * also keeps automations:resume from re-queuing it inside the backoff.
     */
    private function releaseClaim(AutomationEmailDelivery $delivery): void
    {
        AutomationEmailDelivery::query()
            ->whereKey($delivery->id)
            ->where('status', EmailDeliveryStatus::Sending)
            ->update([
                'status' => EmailDeliveryStatus::Queued,
                'send_attempted_at' => null,
            ]);
    }

    /**
     * Close a delivery that will not be sent. Only a queued one: a delivery
     * already handed to the transport keeps its outcome. Returns false when
     * another job already owns or settled the delivery; that job reports the
     * step's outcome, so the caller must leave the step alone.
     */
    private function close(AutomationEmailDelivery $delivery, EmailDeliveryStatus $status, string $reason): bool
    {
        return AutomationEmailDelivery::query()
            ->whereKey($delivery->id)
            ->where('status', EmailDeliveryStatus::Queued)
            ->update([
                'status' => $status,
                'failure_reason' => $reason,
            ]) === 1;
    }
}
