<?php

namespace App\Services;

use App\Enums\ContactCompanyAssignmentMode;
use App\Models\CompanyDomain;
use App\Models\Contact;
use App\Models\Team;
use Illuminate\Support\Str;

class ResolveContactCompany
{
    /** @var list<string> */
    public const array PERSONAL_EMAIL_DOMAINS = [
        'aol.com',
        'gmail.com',
        'hotmail.com',
        'icloud.com',
        'live.com',
        'msn.com',
        'outlook.com',
        'proton.me',
        'protonmail.com',
        'yahoo.com',
    ];

    public function handle(Contact $contact): Contact
    {
        if ($contact->company_assignment_mode === ContactCompanyAssignmentMode::Manual) {
            return $contact;
        }

        $companyId = $this->companyIdFor($contact->team_id, $contact->email);

        if ($contact->company_id !== $companyId) {
            $contact->update(['company_id' => $companyId]);
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
        $domain = Str::afterLast($email, '@');

        if ($domain === '' || self::isPersonalEmailDomain($domain)) {
            return null;
        }

        return CompanyDomain::query()
            ->where('team_id', $teamId)
            ->where('domain', $domain)
            ->value('company_id');
    }
}
