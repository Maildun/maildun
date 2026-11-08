<?php

namespace App\Jobs;

use App\Actions\Emails\RenderCampaignContent;
use App\Concerns\PacesCampaignLoading;
use App\Enums\EmailDeliveryStatus;
use App\Exceptions\EmailTransportException;
use App\Models\Email;
use App\Models\EmailDelivery;
use App\Models\Subscriber;
use App\Services\TeamMailer;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Str;

/**
 * Loads one page of a campaign's recipients into its batch.
 *
 * A loader that fails for good leaves the campaign sending: the deliveries it
 * already queued still go out, and when the batch finishes ResolveCampaignOutcome
 * reports Failed because fewer deliveries exist than recipients, which offers
 * the rest through QueueRemainingRecipients. Failing the campaign here would
 * strand those queued deliveries, since a delivery is only claimed while its
 * campaign is sending.
 */
class PrepareEmailSendChunk implements ShouldQueue
{
    use Batchable, PacesCampaignLoading, Queueable;

    private const int CHUNK_SIZE = 200;

    public int $tries = 3;

    public int $timeout = 45;

    /** @var list<int> */
    public array $backoff = [30, 120, 300];

    public function __construct(
        public int $emailId,
        public int $afterSubscriberId = 0,
    ) {
        $this->onQueue(config('delivery.queues.campaigns'));
    }

    /**
     * Snapshot one bounded recipient page and hydrate the campaign batch with
     * delivery jobs plus the next loader page. This keeps the web request and
     * every worker invocation at constant memory regardless of audience size.
     */
    public function handle(RenderCampaignContent $renderer, TeamMailer $teamMailer): void
    {
        $batch = $this->batch();

        if ($batch === null || $batch->cancelled()) {
            return;
        }

        $email = Email::query()
            ->with(['team', 'audience', 'segment'])
            ->findOrFail($this->emailId);

        if (! $email->status->isActive() || $this->waitForCampaignBacklog($email)) {
            return;
        }

        $transport = $teamMailer->resolve($email->team);

        if (! $transport->allowsSender($email->resolvedFromAddress())) {
            throw EmailTransportException::unauthorizedSender();
        }

        $subscribers = $this->subscribers($email)->get();

        if ($subscribers->isEmpty()) {
            $email->update(['recipient_count' => $email->deliveries()->count()]);

            return;
        }

        $sentAt = $email->send_started_at ?? now();
        $createdAt = now();
        $sendRunId = $email->sendRuns()->latest('id')->value('id');
        $attributeKeys = RenderCampaignContent::attributeKeys($email->audience_id);
        $rows = $subscribers->map(fn (Subscriber $subscriber): array => [
            'uuid' => (string) Str::uuid(),
            'email_id' => $email->id,
            'email_send_run_id' => $sendRunId,
            'subscriber_id' => $subscriber->id,
            'contact_id' => $subscriber->contact_id,
            'email_address' => $subscriber->email,
            'first_name' => $subscriber->first_name,
            'last_name' => $subscriber->last_name,
            'merge_data' => json_encode($renderer->mergeData($subscriber, $sentAt, $attributeKeys), JSON_THROW_ON_ERROR),
            'status' => EmailDeliveryStatus::Queued->value,
            'provider' => $transport->provider->value,
            'uses_team_email_integration' => true,
            'opens_count' => 0,
            'clicks_count' => 0,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ])->all();

        EmailDelivery::query()->insertOrIgnore($rows);

        $deliveryJobs = EmailDelivery::query()
            ->where('email_id', $email->id)
            ->whereIn('subscriber_id', $subscribers->modelKeys())
            ->pluck('id')
            ->map(fn (int $deliveryId): SendEmailDelivery => new SendEmailDelivery($deliveryId))
            ->all();

        if ($subscribers->count() === self::CHUNK_SIZE) {
            $loaderJobs = [new self($email->id, (int) $subscribers->last()->id)];
        } else {
            $loaderJobs = [];
            $email->update(['recipient_count' => $email->deliveries()->count()]);
        }

        $batch->add([...$deliveryJobs, ...$loaderJobs]);
    }

    /** @return list<string> */
    public function tags(): array
    {
        return ['campaign:'.$this->emailId, 'campaign-preparation'];
    }

    /** @return Builder<Subscriber> */
    private function subscribers(Email $email): Builder
    {
        $relation = $email->segment_id !== null
            ? $email->segment->subscribers()
            : $email->audience->subscribers();

        return $relation
            ->getQuery()
            ->with([
                'contact:id',
                // Personalized sections read each recipient's segments and tags.
                'segments' => fn (Relation $query) => $query->select(['segments.id', 'segments.uuid']),
                'tags' => fn (Relation $query) => $query->select(['tags.id', 'tags.uuid']),
            ])
            ->select('subscribers.*')
            ->sendableFor($email->team)
            ->where('subscribers.id', '>', $this->afterSubscriberId)
            ->orderBy('subscribers.id')
            ->limit(self::CHUNK_SIZE);
    }
}
