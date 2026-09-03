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
                ->map(fn (string $domain): string => Str::lower(Str::of($domain)->trim()->toString()))
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
            'domains.*' => ['required', 'string', 'max:253', 'regex:/^(?=.{1,253}$)([a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/'],
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

                $team = $this->route('current_team');
                $company = $this->route('company');

                abort_unless($team instanceof Team, 404);

                $normalizedName = Str::lower($this->string('name')->toString());
                $nameQuery = $team->companies()->where('normalized_name', $normalizedName);

                if ($company instanceof Company) {
                    $nameQuery->whereKeyNot($company);
                }

                if ($nameQuery->exists()) {
                    $validator->errors()->add('name', __('A company with this name already exists.'));
                }

                foreach ($this->array('domains') as $index => $domain) {
                    if (! is_string($domain)) {
                        continue;
                    }

                    if (ResolveContactCompany::isPersonalEmailDomain($domain)) {
                        $validator->errors()->add("domains.{$index}", __('Use a company domain instead of a personal email provider.'));
                    }
                }

                if (count(array_unique($this->array('domains'))) !== count($this->array('domains'))) {
                    $validator->errors()->add('domains', __('Each domain may only be added once.'));
                }

                $domainQuery = CompanyDomain::query()
                    ->where('team_id', $team->id)
                    ->whereIn('domain', $this->array('domains'));

                if ($company instanceof Company) {
                    $domainQuery->where('company_id', '!=', $company->id);
                }

                if ($domainQuery->exists()) {
                    $validator->errors()->add('domains', __('A domain is already assigned to another company.'));
                }
            },
        ];
    }
}
