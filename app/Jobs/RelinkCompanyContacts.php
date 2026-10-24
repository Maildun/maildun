<?php

namespace App\Jobs;

use App\Services\ResolveContactCompany;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Re-links the automatic contacts on company domains that were added, removed or
 * freed, outside the request. It reads the current domain owners when it runs, so
 * retries and out-of-order runs still converge on the right company.
 *
 * The timeout stays under the Redis queue's retry_after (90s) so a slow run is
 * never handed to a second worker while it is still going. Each chunk commits on
 * its own and the next attempt only selects contacts still on the wrong company,
 * so a run that times out resumes where it stopped on its next try.
 */
class RelinkCompanyContacts implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public int $timeout = 60;

    /**
     * @param  list<string>  $domains
     */
    public function __construct(
        public int $teamId,
        public array $domains,
    ) {}

    public function handle(ResolveContactCompany $resolveCompany): void
    {
        $resolveCompany->relinkDomains($this->teamId, $this->domains);
    }
}
