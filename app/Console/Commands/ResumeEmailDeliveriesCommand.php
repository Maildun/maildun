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
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;

#[Signature('emails:resume')]
#[Description('Re-queue email deliveries whose job was lost, and close campaigns stuck mid-send')]
class ResumeEmailDeliveriesCommand extends Command
{
    /**
     * A campaign leans on a Redis batch to move every delivery. Redis is not
     * durable, so a flush, an eviction, or a queue reset on deploy strands the
     * campaign at Sending with no jobs left and no failure recorded — and the
     * report refuses to retry it, because retrying is blocked while a campaign
     * is still active. FinalizeEmailSend only runs if the batch itself
     * survives, so it cannot recover this on its own.
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

        if ($requeued + $failed + $closed + $transactional > 0) {
            $this->info(__(
                ':requeued campaign delivery(s) re-queued, :failed closed as failed, :closed campaign(s) finalized, :transactional transactional delivery(s) re-queued.',
                [
                    'requeued' => $requeued,
                    'failed' => $failed,
                    'closed' => $closed,
                    'transactional' => $transactional,
                ],
            ));
        }

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
     * Finalize campaigns whose batch is gone or finished while the campaign is
     * still marked active, once nothing is left in flight.
     */
    private function closeFinishedCampaigns(CarbonInterface $stalledBefore): int
    {
        $closed = 0;

        Email::query()
            ->whereIn('status', EmailStatus::active())
            ->whereNotNull('send_started_at')
            ->where('send_started_at', '<=', $stalledBefore)
            ->orderBy('id')
            ->chunkById(100, function ($emails) use (&$closed): void {
                foreach ($emails as $email) {
                    if ($this->batchIsStillRunning($email)) {
                        continue;
                    }

                    $inFlight = $email->deliveries()
                        ->whereIn('status', [EmailDeliveryStatus::Queued, EmailDeliveryStatus::Sending])
                        ->exists();

                    if ($inFlight) {
                        continue;
                    }

                    (new FinalizeEmailSend($email->id))(Bus::findBatch((string) $email->batch_id));
                    $closed++;
                }
            });

        return $closed;
    }

    private function batchIsStillRunning(Email $email): bool
    {
        if ($email->batch_id === null) {
            return false;
        }

        $batch = Bus::findBatch($email->batch_id);

        return $batch !== null && ! $batch->finished();
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
}
