<?php

namespace App\Jobs;

use App\Services\ResolveContactCompany;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Re-links the automatic contacts on company domains that were added, removed or
 * freed, outside the request. It reads the current domain owners when it runs, so
 * retries and out-of-order runs still converge on the right company.
 */
class RelinkCompanyContacts implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 600;

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
