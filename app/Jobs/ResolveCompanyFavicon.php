<?php

namespace App\Jobs;

use App\Models\Company;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Throwable;

class ResolveCompanyFavicon implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 30;

    public function __construct(
        public int $companyId,
        public string $domain,
    ) {}

    public function handle(): void
    {
        $company = $this->currentCompany();

        if ($company === null) {
            return;
        }

        try {
            $response = Http::accept('image/*')
                ->connectTimeout(3)
                ->timeout(8)
                // Laravel passes null for unsuccessful non-error responses such as a 3xx.
                ->retry([100, 500], 0, function (?Throwable $exception): bool {
                    return $exception instanceof ConnectionException
                        || ($exception instanceof RequestException
                            && ($exception->response->serverError() || $exception->response->status() === 429));
                }, throw: false)
                ->get(Company::faviconUrlFor($this->domain));
        } catch (ConnectionException) {
            $this->storeFallback($company);

            return;
        }

        if (! $response->successful() || ! str_starts_with($response->header('Content-Type'), 'image/')) {
            $this->storeFallback($company);

            return;
        }

        $company->favicon = Company::faviconUrlFor($this->domain);
        $company->save();
    }

    /**
     * The company, unless it was deleted or its favicon domain changed since this
     * lookup was queued; a newer lookup for the current domain owns the favicon then.
     */
    private function currentCompany(): ?Company
    {
        $company = Company::query()->find($this->companyId);

        if ($company === null || $company->faviconDomain() !== $this->domain) {
            return null;
        }

        return $company;
    }

    private function storeFallback(Company $company): void
    {
        $company->favicon = null;
        $company->save();
    }
}
