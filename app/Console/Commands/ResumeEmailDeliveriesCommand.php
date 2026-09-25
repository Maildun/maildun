<?php

namespace App\Console\Commands;

use App\Actions\Emails\FinalizeEmailSend;
use App\Enums\EmailDeliveryStatus;
use App\Enums\EmailStatus;
use App\Jobs\SendEmailDelivery;
use App\Jobs\SendTransactionalEmailDelivery;
use App\Models\Email;
use App\Models\EmailDelivery;
use App\Models\EmailDeliveryAttempt;
use App\Models\TransactionalEmailDelivery;
use Carbon\CarbonInterface;
use Illuminate\Bus\Batch;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

#[Signature('emails:resume')]
#[Description('Re-queue email deliveries whose job was lost, and close campaigns stuck mid-send')]
class ResumeEmailDeliveriesCommand extends Command
{
    /**
     * Cache key holding when the sweep last completed, for System Check.
     */
    public const string LAST_RUN_CACHE_KEY = 'emails:resume:last-run-at';

    /**
     * A campaign leans on a queued batch to move every delivery. The jobs
     * live in Redis, which is not durable, so a flush, an eviction, or a queue
     * reset on deploy strands the campaign at Sending with no jobs left and no
     * failure recorded — and the report refuses to retry it, because retrying
     * is blocked while a campaign is still active. FinalizeEmailSend only runs
     * when the batch finishes, so it cannot recover this on its own.
     *
     * A delivery still Queued with no send_attempted_at was never handed to a
     * transport, so re-dispatching it is safe: claim() takes it with a
     * conditional update and a racing duplicate loses and returns.
     *
     * A delivery already claimed for Sending is never re-queued. The transport
     * may have accepted it, and nothing here can tell. Those are failed the
     * same way FinalizeEmailSend fails them, so an operator can retry them
     * deliberately from the report.
     */
    public function handle(): int
    {
        $stalledBefore = now()->subMinutes(max(1, (int) config('delivery.recovery.stalled_after_minutes')));

        $requeued = $this->requeueCampaignDeliveries($stalledBefore);
        $failed = $this->failClaimedCampaignDeliveries($stalledBefore);
        $closed = $this->closeFinishedCampaigns($stalledBefore);
        $transactional = $this->requeueTransactionalDeliveries($stalledBefore);
        $transactionalFailed = $this->failClaimedTransactionalDeliveries($stalledBefore);

        if ($requeued + $failed + $closed + $transactional + $transactionalFailed > 0) {
            $this->info(__(
                ':requeued campaign delivery(s) re-queued, :failed closed as failed, :closed campaign(s) finalized, :transactional transactional delivery(s) re-queued, :transactionalFailed closed as failed.',
                [
                    'requeued' => $requeued,
                    'failed' => $failed,
                    'closed' => $closed,
                    'transactional' => $transactional,
                    'transactionalFailed' => $transactionalFailed,
                ],
            ));
        }

        Cache::forever(self::LAST_RUN_CACHE_KEY, now()->toIso8601String());

        return self::SUCCESS;
    }

    /**
     * Deliveries that never reached a transport go straight back on the queue.
     */
    private function requeueCampaignDeliveries(CarbonInterface $stalledBefore): int
    {
        $requeued = 0;

        EmailDelivery::query()
            ->where('status', EmailDeliveryStatus::Queued)
            ->whereNull('send_attempted_at')
            ->where('updated_at', '<=', $stalledBefore)
            ->whereHas('email', fn (Builder $emails) => $emails->whereIn('status', EmailStatus::active()))
            ->orderBy('id')
            ->chunkById(200, function ($deliveries) use (&$requeued): void {
                foreach ($deliveries as $delivery) {
                    SendEmailDelivery::dispatch($delivery->id);
                    $requeued++;
                }
            });

        return $requeued;
    }

    /**
     * Close deliveries stuck mid-handoff. Never re-queue these: the transport
     * may already have accepted the message.
     */
    private function failClaimedCampaignDeliveries(CarbonInterface $stalledBefore): int
    {
        $failureReason = __('The delivery did not report back before the send stalled.');

        return DB::transaction(function () use ($stalledBefore, $failureReason): int {
            $stalled = EmailDelivery::query()
                ->where('status', EmailDeliveryStatus::Sending)
                ->whereNotNull('send_attempted_at')
                ->where('send_attempted_at', '<=', $stalledBefore)
                ->pluck('id')
                ->all();

            if ($stalled === []) {
                return 0;
            }

            EmailDeliveryAttempt::query()
                ->whereIn('email_delivery_id', $stalled)
                ->where('status', EmailDeliveryStatus::Sending)
                ->update(['status' => EmailDeliveryStatus::Failed, 'failure_reason' => $failureReason]);

            return EmailDelivery::query()
                ->whereIn('id', $stalled)
                ->where('status', EmailDeliveryStatus::Sending)
                ->update(['status' => EmailDeliveryStatus::Failed, 'failure_reason' => $failureReason]);
        }, attempts: 3);
    }

    /**
     * Finalize campaigns whose batch is gone, finished, or abandoned while the
     * campaign is still marked active, once nothing is left in flight.
     */
    private function closeFinishedCampaigns(CarbonInterface $stalledBefore): int
    {
        $closed = 0;
        $campaignQueueIsEmpty = null;

        Email::query()
            ->whereIn('status', EmailStatus::active())
            ->whereNotNull('send_started_at')
            ->where('send_started_at', '<=', $stalledBefore)
            ->orderBy('id')
            ->chunkById(100, function ($emails) use (&$closed, &$campaignQueueIsEmpty, $stalledBefore): void {
                foreach ($emails as $email) {
                    $inFlight = $email->deliveries()
                        ->whereIn('status', [EmailDeliveryStatus::Queued, EmailDeliveryStatus::Sending])
                        ->exists();

                    if ($inFlight) {
                        continue;
                    }

                    $batch = $email->batch_id === null ? null : Bus::findBatch($email->batch_id);

                    if ($batch !== null && ! $batch->finished()) {
                        $campaignQueueIsEmpty ??= Queue::size(config('delivery.queues.campaigns')) === 0;

                        if (! $campaignQueueIsEmpty || ! $this->batchWasAbandoned($email, $batch, $stalledBefore)) {
                            continue;
                        }

                        $batch->cancel();
                    }

                    (new FinalizeEmailSend($email->id))($batch);
                    $closed++;
                }
            });

        return $closed;
    }

    /**
     * The batch record lives in the database, so a Redis flush that loses its
     * jobs leaves it unfinished forever: its pending count can never reach
     * zero. The caller has already seen an empty campaign queue (which counts
     * delayed and reserved jobs too) and no queued or sending deliveries, so
     * the batch is abandoned once it and its last claim are older than the
     * stall window. A queue that is merely waiting for a worker is never
     * empty, so this cannot close a campaign whose jobs still exist.
     */
    private function batchWasAbandoned(Email $email, Batch $batch, CarbonInterface $stalledBefore): bool
    {
        if ($batch->createdAt->greaterThan($stalledBefore)) {
            return false;
        }

        return ! $email->deliveries()
            ->where('send_attempted_at', '>', $stalledBefore)
            ->exists();
    }

    private function requeueTransactionalDeliveries(CarbonInterface $stalledBefore): int
    {
        $requeued = 0;

        TransactionalEmailDelivery::query()
            ->where('status', EmailDeliveryStatus::Queued)
            ->whereNull('send_attempted_at')
            ->where('updated_at', '<=', $stalledBefore)
            ->orderBy('id')
            ->chunkById(200, function ($deliveries) use (&$requeued): void {
                foreach ($deliveries as $delivery) {
                    SendTransactionalEmailDelivery::dispatch($delivery->id);
                    $requeued++;
                }
            });

        return $requeued;
    }

    /**
     * Close transactional deliveries stuck mid-handoff. A retry after a killed
     * or timed-out worker loses the claim and returns without failing, so
     * nothing else ever moves these rows. Never re-queue them: the transport
     * may already have accepted the message.
     */
    private function failClaimedTransactionalDeliveries(CarbonInterface $stalledBefore): int
    {
        return TransactionalEmailDelivery::query()
            ->where('status', EmailDeliveryStatus::Sending)
            ->whereNotNull('send_attempted_at')
            ->where('send_attempted_at', '<=', $stalledBefore)
            ->update([
                'status' => EmailDeliveryStatus::Failed,
                'failure_reason' => __('The delivery did not report back before the send stalled.'),
            ]);
    }
}
