<?php

namespace App\Actions\Companies;

use App\Enums\ContactCompanyAssignmentMode;
use App\Jobs\RelinkCompanyContacts;
use App\Models\Company;
use App\Models\Team;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DeleteCompany
{
    /**
     * Delete the company and release its contacts. Automatic contacts are on this
     * company's domains, which no other company owns, so they simply lose the
     * company. Manually assigned contacts turn automatic and are re-linked in a
     * queued job, since their own email domain may match another company.
     */
    public function handle(Team $team, Company $company): void
    {
        DB::transaction(function () use ($team, $company): void {
            $manualContactDomains = array_values($company->contacts()
                ->where('company_assignment_mode', ContactCompanyAssignmentMode::Manual)
                ->pluck('email')
                ->map(fn (string $email): string => Str::afterLast($email, '@'))
                ->unique()
                ->all());

            $company->contacts()->update([
                'company_id' => null,
                'company_assignment_mode' => ContactCompanyAssignmentMode::Automatic,
            ]);
            $company->delete();

            if ($manualContactDomains !== []) {
                RelinkCompanyContacts::dispatch($team->id, $manualContactDomains)->afterCommit();
            }
        });
    }
}
