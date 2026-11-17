<?php

namespace App\Concerns;

use Illuminate\Support\Facades\Cache;

/**
 * Lets only one campaign loader work a given page of a batch.
 *
 * A loader adds its delivery jobs and the next page's loader to the batch
 * before the queue acknowledges it. If its worker dies in between, the queue
 * retries it and the next page is added a second time, and every page after
 * that doubles too. Claims keep those duplicate sends from going out, but the
 * queue work grows. Each loader carries a token that survives its own retries
 * and releases; the first copy to start claims the page and any other copy
 * stops. When the claim cannot be kept (the cache was flushed) both copies
 * run, which is the old behaviour and still safe.
 */
trait ClaimsCampaignLoaderPage
{
    /**
     * Identifies this loader across its own retries and releases. Null on a
     * loader queued before tokens existed, which never stands aside.
     */
    public ?string $loaderToken = null;

    protected function anotherLoaderOwnsPage(string $page): bool
    {
        $batch = $this->batch();

        if ($this->loaderToken === null || $batch === null) {
            return false;
        }

        $key = 'campaign-loader:'.$batch->id.':'.$page;

        if (Cache::add($key, $this->loaderToken, now()->addDay())) {
            return false;
        }

        return Cache::get($key) !== $this->loaderToken;
    }
}
