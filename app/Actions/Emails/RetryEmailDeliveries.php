<?php

namespace App\Actions\Emails;

use App\Enums\EmailDeliveryStatus;
use App\Enums\EmailSendRunKind;
use App\Enums\EmailStatus;
use App\Exceptions\EmailTransportException;
use App\Jobs\PrepareEmailRetryChunk;
use App\Jobs\SendEmailDelivery;
use App\Models\Email;
use App\Models\EmailDelivery;
use App\Models\EmailSendRun;
use App\Services\TeamMailer;
use Illuminate\Bus\Batch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RetryEmailDeliveries
{
    /**
     * How many deliveries one page re-arms and queues.
     */
    public const int PAGE_SIZE = 200;

    public function __construct(private TeamMailer $teamMailer) {}

    /**
     * Unconfirmed deliveries (claimed, handed to the transport, never
     * confirmed) are skipped unless the caller opts in, because the provider
     * may already have delivered them.
     *
     * The request re-arms only the first page, so a retry after an outage
     * holds a bounded lock and queues a bounded batch however many deliveries
     * failed. A PrepareEmailRetryChunk in the same batch pages through the
     * rest by delivery id, the way the send loader pages through recipients.
     *
     * @param  list<int>|null  $deliveryIds
     */
    public function handle(Email $email, ?array $deliveryIds = null, bool $includeUnconfirmed = false): Batch
    {
        [$sendRun, $firstPage] = DB::transaction(function () use ($email, $deliveryIds, $includeUnconfirmed): array {
            $lockedEmail = Email::query()->with('team')->lockForUpdate()->findOrFail($email->id);

            if ($lockedEmail->status === EmailStatus::Draft) {
                throw ValidationException::withMessages(['email' => __('This campaign has not been sent.')]);
            }

            if ($lockedEmail->status->isActive()) {
                throw ValidationException::withMessages(['email' => __('Wait until this campaign finishes sending before retrying.')]);
            }

            $retryable = $this->retryable($lockedEmail, $includeUnconfirmed, $deliveryIds);

            if (! (clone $retryable)->exists()) {
                $this->failNothingToRetry($lockedEmail, $includeUnconfirmed, $deliveryIds);
            }

            try {
                $transport = $this->teamMailer->resolve($lockedEmail->team);
            } catch (EmailTransportException) {
                throw ValidationException::withMessages(['email' => __('Connect an email provider before retrying deliveries.')]);
            }

            $fromAddress = $lockedEmail->resolvedFromAddress();

            if (! $transport->allowsSender($fromAddress)) {
                throw ValidationException::withMessages(['email' => __(
                    'The From address :address is not verified for the current email delivery connection. Retest this sender before retrying.',
                    ['address' => $fromAddress],
                )]);
            }

            $sendRun = $lockedEmail->sendRuns()->create([
                'kind' => EmailSendRunKind::Retry,
                'recipient_count' => (clone $retryable)->count(),
                'started_at' => now(),
            ]);

            $firstPage = $this->rearmPage($lockedEmail, $sendRun, $includeUnconfirmed, $deliveryIds, 0);

            if ($firstPage === []) {
                $this->failNothingToRetry($lockedEmail, $includeUnconfirmed, $deliveryIds);
            }

            $lockedEmail->update(['status' => EmailStatus::Sending]);

            return [$sendRun, $firstPage];
        });

        $jobs = array_map(fn (int $deliveryId): SendEmailDelivery => new SendEmailDelivery($deliveryId), $firstPage);

        if (count($firstPage) === self::PAGE_SIZE) {
            $jobs[] = new PrepareEmailRetryChunk($email->id, $sendRun->id, $includeUnconfirmed, $deliveryIds, (int) end($firstPage));
        }

        $batch = Bus::batch($jobs)
            ->name('Campaign retry: '.$email->name)
            ->onQueue(config('delivery.queues.campaigns'))
            ->allowFailures()
            ->before(new RecordCampaignBatch($email->id))
            ->finally(new FinalizeEmailSend($email->id))
            ->dispatch();

        $email->update(['batch_id' => $batch->id]);

        return $batch;
    }

    /**
     * Queue the next page of a retry run again: reset up to PAGE_SIZE still
     * retryable deliveries after the given id and point them at the run. Only
     * that page is locked. Returns the re-armed ids in order.
     *
     * @param  list<int>|null  $deliveryIds
     * @return list<int>
     */
    public function rearmPage(
        Email $email,
        EmailSendRun $sendRun,
        bool $includeUnconfirmed,
        ?array $deliveryIds,
        int $afterDeliveryId,
    ): array {
        return DB::transaction(function () use ($email, $sendRun, $includeUnconfirmed, $deliveryIds, $afterDeliveryId): array {
            /** @var list<int> $page */
            $page = $this->retryable($email, $includeUnconfirmed, $deliveryIds)
                ->where('email_deliveries.id', '>', $afterDeliveryId)
                ->orderBy('email_deliveries.id')
                ->limit(self::PAGE_SIZE)
                ->lockForUpdate()
                ->pluck('email_deliveries.id')
                ->map(fn (mixed $deliveryId): int => (int) $deliveryId)
                ->all();

            if ($page === []) {
                return [];
            }

            EmailDelivery::query()
                ->whereKey($page)
                ->update([
                    'email_send_run_id' => $sendRun->id,
                    'status' => EmailDeliveryStatus::Queued,
                    'failure_reason' => null,
                    'failure_code' => null,
                    'provider_message_id' => null,
                    'send_attempted_at' => null,
                    'sent_at' => null,
                    'delivered_at' => null,
                    'delayed_at' => null,
                ]);

            return $page;
        }, attempts: 3);
    }

    /**
     * The deliveries this retry covers: retryable for the workspace, limited to
     * the chosen ones, and without unconfirmed sends unless the caller opted in.
     *
     * @param  list<int>|null  $deliveryIds
     * @return HasMany<EmailDelivery, Email>
     */
    private function retryable(Email $email, bool $includeUnconfirmed, ?array $deliveryIds): HasMany
    {
        return $email->deliveries()
            ->retryableFor($email->team)
            ->when($deliveryIds !== null, fn (Builder $deliveries) => $deliveries->whereIn('email_deliveries.id', $deliveryIds))
            ->when(
                ! $includeUnconfirmed,
                fn (Builder $deliveries) => $deliveries->whereNot(fn (Builder $unconfirmed) => $unconfirmed->unconfirmed()),
            );
    }

    /**
     * @param  list<int>|null  $deliveryIds
     */
    private function failNothingToRetry(Email $email, bool $includeUnconfirmed, ?array $deliveryIds): never
    {
        $unconfirmedWereSkipped = ! $includeUnconfirmed
            && $this->retryable($email, true, $deliveryIds)->unconfirmed()->exists();

        throw ValidationException::withMessages(['email' => $unconfirmedWereSkipped
            ? __('These deliveries may already have reached their recipients. Confirm that you want to send them again.')
            : __('There are no failed deliveries that can be retried.')]);
    }
}
