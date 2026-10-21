<?php

namespace App\Http\Requests;

use App\Models\Company;
use App\Models\CompanyDomain;
use App\Models\Team;
use App\Services\ResolveContactCompany;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Validator;

class SaveCompanyRequest extends FormRequest
{
    public function authorize(): bool
    {
        $company = $this->route('company');

        return $company instanceof Company
            ? Gate::allows('update', $company)
            : Gate::allows('create', [Company::class, $this->route('current_team')]);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => Str::squish($this->string('name')->toString()),
            'domains' => collect($this->array('domains'))
                ->filter(fn (mixed $domain): bool => is_string($domain))
                ->map(fn (string $domain): string => CompanyDomain::normalize($domain))
                ->values()
                ->all(),
        ]);
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
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

    /** @return list<Closure(Validator): void> */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                foreach ($this->array('domains') as $index => $domain) {
                    if (! is_string($domain)) {
                        continue;
                    }

                    if (ResolveContactCompany::isPersonalEmailDomain($domain)) {
                        $validator->errors()->add("domains.{$index}", __(':domain is a personal email provider. Use a company domain instead.', ['domain' => $domain]));
                    }
                }

                if (count(array_unique($this->array('domains'))) !== count($this->array('domains'))) {
                    $validator->errors()->add('domains', __('Each domain may only be added once.'));
                }

                foreach ($this->conflictErrors() as $key => $message) {
                    $validator->errors()->add($key, $message);
                }
            },
        ];
    }

    /**
     * Errors for a name or domains another company in the workspace already owns.
     * Also used after a save loses a unique-key race with a concurrent request.
     *
     * @return array<string, string>
     */
    public function conflictErrors(): array
    {
        $team = $this->route('current_team');
        $company = $this->route('company');

        abort_unless($team instanceof Team, 404);

        $errors = [];
        $nameQuery = $team->companies()->where('normalized_name', Company::normalizeName($this->string('name')->toString()));

        if ($company instanceof Company) {
            $nameQuery->whereKeyNot($company);
        }

        if ($nameQuery->exists()) {
            $errors['name'] = __('A company with this name already exists.');
        }

        $domainQuery = CompanyDomain::query()
            ->where('team_id', $team->id)
            ->whereIn('domain', $this->array('domains'));

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
