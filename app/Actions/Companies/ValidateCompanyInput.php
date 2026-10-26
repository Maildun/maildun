<?php

namespace App\Actions\Companies;

use App\Models\Company;
use App\Models\CompanyDomain;
use App\Models\Team;
use App\Services\ResolveContactCompany;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * The company name and domain rules shared by the web company form and the MCP
 * company tools, so both normalize, validate and detect conflicts the same way.
 */
class ValidateCompanyInput
{
    /**
     * Normalize and validate company input outside an HTTP form request, applying
     * the same rules, messages and checks as SaveCompanyRequest.
     *
     * @return array{name: string, domains: list<string>}
     *
     * @throws ValidationException
     */
    public function validate(Team $team, ?Company $company, mixed $name, mixed $domains): array
    {
        $input = $this->normalize($name, $domains);

        Validator::make($input, $this->rules(), $this->messages(), $this->attributes())->validate();

        $errors = $this->errors($team, $company, $input['name'], $input['domains']);

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $input;
    }

    /**
     * Squish the name and normalize each domain the way contact email domains are
     * stored, dropping anything that is not a string.
     *
     * @return array{name: mixed, domains: list<string>}
     */
    public function normalize(mixed $name, mixed $domains): array
    {
        return [
            'name' => is_string($name) ? Str::squish($name) : $name,
            'domains' => array_values(array_map(
                fn (string $domain): string => CompanyDomain::normalize($domain),
                array_filter((array) $domains, fn (mixed $domain): bool => is_string($domain)),
            )),
        ];
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'domains' => ['required', 'array', 'min:1', 'max:20'],
            'domains.*' => ['required', 'string', 'max:253', 'regex:/^(?=.{1,253}$)([a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+(?:[a-z]{2,63}|xn--[a-z0-9-]{1,59})$/'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'domains.*.regex' => __(':input is not a valid domain. Enter an email domain such as acme.com.'),
            'domains.*.max' => __(':input is too long to be a domain.'),
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'domains.*' => __('domain'),
        ];
    }

    /**
     * Errors the basic rules cannot express: personal email providers, repeated
     * domains, and a name or domains another company in the workspace owns. Run
     * only after the basic rules pass.
     *
     * @param  list<string>  $domains
     * @return array<string, list<string>>
     */
    public function errors(Team $team, ?Company $company, string $name, array $domains): array
    {
        $errors = [];

        foreach ($domains as $index => $domain) {
            if (ResolveContactCompany::isPersonalEmailDomain($domain)) {
                $errors["domains.{$index}"][] = __(':domain is a personal email provider. Use a company domain instead.', ['domain' => $domain]);
            }
        }

        if (count(array_unique($domains)) !== count($domains)) {
            $errors['domains'][] = __('Each domain may only be added once.');
        }

        foreach ($this->conflictErrors($team, $company, $name, $domains) as $key => $message) {
            $errors[$key][] = $message;
        }

        return $errors;
    }

    /**
     * Errors for a name or domains another company in the workspace already owns.
     * Also used after a save loses a unique-key race with a concurrent request.
     *
     * @param  list<string>  $domains
     * @return array<string, string>
     */
    public function conflictErrors(Team $team, ?Company $company, string $name, array $domains): array
    {
        $errors = [];
        $nameQuery = $team->companies()->where('normalized_name', Company::normalizeName($name));

        if ($company instanceof Company) {
            $nameQuery->whereKeyNot($company);
        }

        if ($nameQuery->exists()) {
            $errors['name'] = __('A company with this name already exists.');
        }

        $domainQuery = CompanyDomain::query()
            ->where('team_id', $team->id)
            ->whereIn('domain', $domains);

        if ($company instanceof Company) {
            $domainQuery->where('company_id', '!=', $company->id);
        }

        $takenDomains = $domainQuery->orderBy('domain')->pluck('domain');

        if ($takenDomains->isNotEmpty()) {
            $errors['domains'] = trans_choice(
                ':domains is already assigned to another company.|:domains are already assigned to other companies.',
                $takenDomains->count(),
                ['domains' => $takenDomains->implode(', ')],
            );
        }

        return $errors;
    }
}
