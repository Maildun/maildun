<?php

namespace App\Jobs;

use App\Actions\Emails\RetryEmailDeliveries;
use App\Concerns\PacesCampaignLoading;
use App\Models\Email;
use App\Models\EmailSendRun;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Re-arms the next page of a retry run and adds its delivery jobs, plus the
 * page after it, to the retry's batch. Paging by delivery id keeps every page
 * bounded however many deliveries an outage failed, and a page only picks up
 * deliveries that are still retryable, so a repeated page re-arms nothing twice.
 *
 * A loader that fails for good leaves the rest of the run as it was: failed,
 * and retryable again once the campaign finishes.
 */
class PrepareEmailRetryChunk implements ShouldQueue
{
    use Batchable, PacesCampaignLoading, Queueable;

    public int $tries = 3;

    public int $timeout = 45;

    /** @var list<int> */
    public array $backoff = [30, 120, 300];

    /**
     * @param  list<int>|null  $deliveryIds
     */
    public function __construct(
        public int $emailId,
        public int $sendRunId,
        public bool $includeUnconfirmed = false,
        public ?array $deliveryIds = null,
        public int $afterDeliveryId = 0,
    ) {
        $this->onQueue(config('delivery.queues.campaigns'));
    }

    /**
     * Execute the job.
     */
    public function handle(RetryEmailDeliveries $retry): void
    {
        $batch = $this->batch();

        if ($batch === null || $batch->cancelled()) {
            return;
        }

        $email = Email::query()->with('team')->find($this->emailId);
        $sendRun = EmailSendRun::query()->find($this->sendRunId);

        if ($email === null || $sendRun === null || ! $email->status->isActive() || $this->waitForCampaignBacklog($email)) {
            return;
        }

        $page = $retry->rearmPage($email, $sendRun, $this->includeUnconfirmed, $this->deliveryIds, $this->afterDeliveryId);
        $jobs = array_map(fn (int $deliveryId): SendEmailDelivery => new SendEmailDelivery($deliveryId), $page);

        if (count($page) === RetryEmailDeliveries::PAGE_SIZE) {
            $jobs[] = new self($this->emailId, $this->sendRunId, $this->includeUnconfirmed, $this->deliveryIds, (int) end($page));
        } else {
            // The run was sized when the retry was requested; deliveries that
            // stopped being retryable since then were not queued.
            $sendRun->update(['recipient_count' => $sendRun->deliveries()->count()]);
        }

        if ($jobs !== []) {
            $batch->add($jobs);
        }
    }

    /** @return list<string> */
    public function tags(): array
    {
        return ['campaign:'.$this->emailId, 'campaign-retry-preparation'];
    }
}
