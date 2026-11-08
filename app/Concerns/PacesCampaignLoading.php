<?php

namespace App\Concerns;

use App\Enums\EmailDeliveryStatus;
use App\Enums\EmailSendLane;
use App\Models\Email;
use DateTimeInterface;

/**
 * Keeps a campaign loader only a few minutes of sending ahead of the send rate.
 *
 * Every delivery job's time to wait for a send slot starts when it is queued.
 * Queueing a whole audience at once starts them all together, so a list larger
 * than the send rate can clear in that window fails its tail for nothing. A
 * loader that waits while its campaign's backlog is full gives every delivery
 * its slot soon after it is queued. Without a send rate there is no window to
 * run out, and loading is as fast as before.
 */
trait PacesCampaignLoading
{
    /**
     * How far ahead of sending a campaign is loaded, in seconds of the bulk rate.
     */
    public const int BACKLOG_SECONDS = 300;

    /**
     * How long a waiting loader sleeps before checking the backlog again.
     */
    public const int PACING_DELAY_SECONDS = 30;

    /**
     * How long one loader may keep waiting for its campaign's backlog to drain.
     */
    public const int PACING_WINDOW_HOURS = 6;

    /**
     * Three genuine failures still fail the loader. Waiting for the backlog
     * releases the job, which consumes attempts but never exceptions.
     */
    public int $maxExceptions = 3;

    /**
     * Each wait for the backlog is a release, so bound a paced loader by time
     * rather than attempts. Without a send rate it never waits, and the plain
     * $tries budget stands.
     */
    public function retryUntil(): ?DateTimeInterface
    {
        return EmailSendLane::Bulk->perSecond() > 0 ? now()->addHours(self::PACING_WINDOW_HOURS) : null;
    }

    /**
     * Release the job to check again later while the campaign already has a
     * full backlog of deliveries waiting for their first claim. A row queued
     * longer than the stall window no longer counts: its job may be lost, and
     * emails:resume only re-queues it once the loader has finished. Returns
     * whether the job was released.
     */
    protected function waitForCampaignBacklog(Email $email): bool
    {
        $perSecond = EmailSendLane::Bulk->perSecond();

        if ($perSecond <= 0) {
            return false;
        }

        $stalledBefore = now()->subMinutes(max(1, (int) config('delivery.recovery.stalled_after_minutes')));
        $backlog = $email->deliveries()
            ->where('status', EmailDeliveryStatus::Queued)
            ->whereNull('send_attempted_at')
            ->where('updated_at', '>', $stalledBefore)
            ->count();

        if ($backlog < $perSecond * self::BACKLOG_SECONDS) {
            return false;
        }

        $this->release(self::PACING_DELAY_SECONDS);

        return true;
    }
}
