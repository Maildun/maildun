<?php

namespace App\Services;

use App\Enums\ContactCompanyAssignmentMode;
use App\Models\CompanyDomain;
use App\Models\Contact;
use App\Models\Team;
use Illuminate\Support\Str;

class ResolveContactCompany
{
    /**
     * Consumer mailbox providers never identify a company. Shares the provider list the
     * import review already maintains so both stay in step.
     *
     * @var list<string>
     */
    public const array PERSONAL_EMAIL_DOMAINS = ImportEmailReview::KNOWN_DOMAINS;

    public function handle(Contact $contact): Contact
    {
        if ($contact->company_assignment_mode === ContactCompanyAssignmentMode::Manual) {
            return $contact;
        }

        $companyId = $this->companyIdFor($contact->team_id, $contact->email);

        if ($contact->company_id === $companyId) {
            return $contact;
        }

        // Only write while the contact is still automatic, so a concurrent switch
        // to a manual company is never overwritten by this re-resolution.
        $updated = Contact::query()
            ->whereKey($contact->getKey())
            ->where('company_assignment_mode', ContactCompanyAssignmentMode::Automatic)
            ->update(['company_id' => $companyId]);

        if ($updated > 0) {
            $contact->forceFill(['company_id' => $companyId])->syncOriginalAttribute('company_id');
        }

        return $contact;
    }

    public function refreshTeam(Team $team): void
    {
        $team->contacts()
            ->where('company_assignment_mode', ContactCompanyAssignmentMode::Automatic)
            ->lazyById()
            ->each(fn (Contact $contact) => $this->handle($contact));
    }

    public static function isPersonalEmailDomain(string $domain): bool
    {
        return in_array(Str::lower($domain), self::PERSONAL_EMAIL_DOMAINS, true);
    }

    private function companyIdFor(int $teamId, string $email): ?int
    {
        $domain = CompanyDomain::toAscii(Str::lower(Str::afterLast($email, '@')));

        if ($domain === '' || self::isPersonalEmailDomain($domain)) {
            return null;
        }

        return CompanyDomain::query()
            ->where('team_id', $teamId)
            ->where('domain', $domain)
            ->value('company_id');
    }
}
