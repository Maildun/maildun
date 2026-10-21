<?php

namespace App\Services;

use App\Enums\ContactCompanyAssignmentMode;
use App\Models\CompanyDomain;
use App\Models\Contact;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
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

    /**
     * Re-resolve the automatic contacts whose email is on one of the given domains.
     * Works in set-based chunks, so a company save never walks every contact.
     *
     * @param  list<string>  $domains
     */
    public function relinkDomains(int $teamId, array $domains): void
    {
        $domains = array_unique(array_map(
            fn (string $domain): string => CompanyDomain::toAscii(Str::lower($domain)),
            $domains,
        ));

        foreach ($domains as $domain) {
            if ($domain === '' || self::isPersonalEmailDomain($domain)) {
                continue;
            }

            $companyId = CompanyDomain::query()
                ->where('team_id', $teamId)
                ->where('domain', $domain)
                ->value('company_id');

            $this->automaticContactsOn($teamId, $domain)
                ->where(fn (Builder $query) => $companyId === null
                    ? $query->whereNotNull('company_id')
                    : $query->whereNull('company_id')->orWhere('company_id', '!=', $companyId))
                ->chunkById(1000, function (Collection $contacts) use ($companyId): void {
                    Contact::query()
                        ->whereKey($contacts->modelKeys())
                        ->where('company_assignment_mode', ContactCompanyAssignmentMode::Automatic)
                        ->update(['company_id' => $companyId]);
                });
        }
    }

    public static function isPersonalEmailDomain(string $domain): bool
    {
        return in_array(Str::lower($domain), self::PERSONAL_EMAIL_DOMAINS, true);
    }

    /**
     * Automatic contacts with an email on the domain, in its punycode or Unicode form.
     *
     * @return Builder<Contact>
     */
    private function automaticContactsOn(int $teamId, string $domain): Builder
    {
        $unicodeDomain = idn_to_utf8($domain, IDNA_NONTRANSITIONAL_TO_UNICODE, INTL_IDNA_VARIANT_UTS46);
        $forms = array_unique(array_filter([$domain, $unicodeDomain === false ? null : $unicodeDomain]));

        return Contact::query()
            ->select('id')
            ->where('team_id', $teamId)
            ->where('company_assignment_mode', ContactCompanyAssignmentMode::Automatic)
            ->where(function (Builder $query) use ($forms): void {
                foreach ($forms as $form) {
                    $query->orWhereLike('email', '%@'.$form);
                }
            });
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
