<?php

namespace App\Http\Requests;

use App\Models\Audience;
use App\Models\Contact;
use App\Models\Subscriber;
use App\Models\Team;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class SaveSubscriberRequest extends FormRequest
{
    public function authorize(): bool
    {
        $subscriber = $this->route('subscriber');

        return $subscriber instanceof Subscriber
            ? Gate::allows('update', $subscriber)
            : Gate::allows('create', [Subscriber::class, $this->route('audience')]);
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('email')) {
            $this->merge(['email' => Str::lower($this->string('email')->trim()->toString())]);
        }
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        $audience = $this->route('audience');
        $subscriber = $this->route('subscriber');
        $team = $this->route('current_team');

        abort_unless($audience instanceof Audience, 404);
        abort_unless($team instanceof Team, 404);

        $rules = [
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique(Subscriber::class)
                    ->where('audience_id', $audience->id)
                    ->ignore($subscriber),
                ...($subscriber instanceof Subscriber ? [
                    Rule::unique(Contact::class)
                        ->where('team_id', $team->id)
                        ->ignore($subscriber->contact_id),
                ] : []),
            ],
            'first_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
            'tags' => ['sometimes', 'array', 'max:20'],
            'tags.*' => ['string', 'max:50'],
        ];

        /**
         * Consent is captured once, when the subscriber is created. Updates never
         * touch consent columns, so the field is ignored rather than validated —
         * the edit dialog submits it as `false` and must not be rejected for it.
         */
        if ($this->isMethod('post')) {
            $rules['consent_confirmed'] = ['required', 'accepted'];
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
}
