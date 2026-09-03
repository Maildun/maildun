<?php

namespace App\Http\Requests;

use App\Enums\ContactCompanyAssignmentMode;
use App\Models\Audience;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Team;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class SaveContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        $contact = $this->route('contact');

        return $contact instanceof Contact
            ? Gate::allows('update', $contact)
            : Gate::allows('create', [Contact::class, $this->route('current_team')]);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => Str::lower($this->string('email')->trim()->toString()),
            'first_name' => $this->blankToNull('first_name'),
            'last_name' => $this->blankToNull('last_name'),
            'company_uuid' => $this->blankToNull('company_uuid'),
        ]);
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        $team = $this->route('current_team');
        $contact = $this->route('contact');

        abort_unless($team instanceof Team, 404);

        $rules = [
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                ...($contact instanceof Contact ? [
                    Rule::unique(Contact::class)->where('team_id', $team->id)->ignore($contact),
                ] : []),
            ],
            'first_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
            'company_assignment_mode' => ['required', Rule::enum(ContactCompanyAssignmentMode::class)],
            'company_uuid' => [
                'nullable',
                'string',
                Rule::exists(Company::class, 'uuid')->where('team_id', $team->id),
            ],
            'tags' => ['sometimes', 'array', 'max:20'],
            'tags.*' => ['string', 'max:50'],
        ];

        if ($this->isMethod('post')) {
            $audienceUuids = $this->input('audience_uuids', []);
            $hasAudienceSelection = is_array($audienceUuids) && count($audienceUuids) > 0;

            $rules['audience_uuids'] = ['sometimes', 'array', 'max:50'];
            $rules['audience_uuids.*'] = [
                'string',
                'distinct',
                Rule::exists(Audience::class, 'uuid')->where('team_id', $team->id),
            ];
            $rules['consent_confirmed'] = $hasAudienceSelection
                ? ['required', 'accepted']
                : ['sometimes', 'accepted'];
        }

        return $rules;
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'email.unique' => __('This email is already used by another contact.'),
        ];
    }

    private function blankToNull(string $key): ?string
    {
        if (! $this->exists($key)) {
            return null;
        }

        $value = $this->input($key);

        return is_string($value) && Str::of($value)->trim()->isNotEmpty()
            ? Str::of($value)->trim()->toString()
            : null;
    }
}
