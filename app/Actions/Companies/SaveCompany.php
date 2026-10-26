<?php

namespace App\Actions\Companies;

use App\Jobs\RelinkCompanyContacts;
use App\Jobs\ResolveCompanyFavicon;
use App\Models\Company;
use App\Models\Team;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaveCompany
{
    public function __construct(private ValidateCompanyInput $validateCompanyInput) {}

    /**
     * Create a company, or update the given one, from validated input in one
     * transaction. Validation checks names and domains without locking, so a
     * concurrent request can still claim one first; report that as the same
     * validation error instead of a server error and a half-saved company.
     *
     * @param  list<string>  $domains
     *
     * @throws ValidationException
     */
    public function handle(Team $team, ?Company $company, string $name, array $domains): Company
    {
        try {
            return DB::transaction(function () use ($team, $company, $name, $domains): Company {
                if ($company instanceof Company) {
                    $company->update(['name' => $name]);
                } else {
                    $company = $team->companies()->create(['name' => $name]);
                }

                $this->syncDomains($company, $team, $domains);

                return $company;
            });
        } catch (UniqueConstraintViolationException) {
            // The winning row may itself have been rolled back by now; still answer
            // with a validation error rather than a server error.
            throw ValidationException::withMessages($this->validateCompanyInput->conflictErrors($team, $company, $name, $domains) ?: [
                'domains' => __('Someone else changed these companies at the same time. Try again.'),
            ]);
        }
    }

    /**
     * Store the submitted domains, then queue a contact re-link for only the domains
     * that were added or removed, and a favicon lookup if the favicon domain changed.
     *
     * @param  list<string>  $domains
     */
    private function syncDomains(Company $company, Team $team, array $domains): void
    {
        $previousDomains = $company->wasRecentlyCreated ? [] : $company->domains()->pluck('domain')->all();
        $previousFaviconDomain = $company->wasRecentlyCreated ? null : $company->faviconDomain();

        $company->domains()->whereNotIn('domain', $domains)->delete();

        foreach ($domains as $domain) {
            $company->domains()->firstOrCreate([
                'team_id' => $team->id,
                'domain' => $domain,
            ]);
        }

        $changedDomains = array_values(array_unique([
            ...array_diff($domains, $previousDomains),
            ...array_diff($previousDomains, $domains),
        ]));
        sort($changedDomains);

        if ($changedDomains !== []) {
            RelinkCompanyContacts::dispatch($team->id, $changedDomains)->afterCommit();
        }

        $this->refreshFavicon($company, $previousFaviconDomain);
    }

    private function refreshFavicon(Company $company, ?string $previousFaviconDomain): void
    {
        $faviconDomain = $company->faviconDomain();

        if ($faviconDomain === null) {
            return;
        }

        if ($faviconDomain !== $previousFaviconDomain) {
            $company->forceFill(['favicon' => null])->save();
        } elseif ($company->favicon !== null) {
            return;
        }

        ResolveCompanyFavicon::dispatch($company->id, $faviconDomain)->afterCommit();
    }
}
