<?php

namespace App\Http\Requests;

use App\Enums\EmailEditor;
use App\Models\Team;
use App\Models\TransactionalEmail;
use App\Rules\AuthorizedSenderAddress;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateTransactionalEmailRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('update', $this->route('transactionalEmail'));
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        $team = $this->route('current_team');
        $email = $this->route('transactionalEmail');

        abort_unless($team instanceof Team, 404);
        abort_unless($email instanceof TransactionalEmail, 404);

        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'required',
                'string',
                'max:64',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique(TransactionalEmail::class)
                    ->where('team_id', $team->id)
                    ->ignore($email),
            ],
            'description' => ['nullable', 'string', 'max:255'],
            'subject' => ['required', 'string', 'max:255'],
            'preheader' => ['nullable', 'string', 'max:255'],
            'from_name' => ['nullable', 'string', 'max:255'],
            'from_address' => ['nullable', 'string', 'email', 'max:255', new AuthorizedSenderAddress($team)],
            'reply_to' => ['nullable', 'string', 'email', 'max:255'],
            'html' => ['required', 'string', 'max:2000000'],
            'source' => [$team->email_editor->usesSource() ? 'required' : 'nullable', 'string', 'max:2000000'],
            'design' => ['nullable', 'array'],
            'design.root' => ['required_with:design', 'array'],
            'variables' => ['nullable', 'array'],
            'variables.*.key' => ['required', 'string', 'max:64', 'regex:/^[a-zA-Z_][a-zA-Z0-9_]*$/'],
            'variables.*.example' => ['nullable', 'string', 'max:255'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'slug.regex' => __('Use lowercase letters, numbers, and hyphens.'),
            'variables.*.key.regex' => __('Variable keys start with a letter or underscore.'),
        ];
    }

    /** @return array<callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $this->validateSlugNotFrozen($validator);
            $this->validateDesignPresentForBuilder($validator);
        }];
    }

    protected function validateSlugNotFrozen(Validator $validator): void
    {
        $email = $this->route('transactionalEmail');

        abort_unless($email instanceof TransactionalEmail, 404);

        if ($email->slugIsFrozen() && $this->string('slug')->value() !== $email->slug) {
            $validator->errors()->add('slug', __('The identifier cannot change after the first publish.'));
        }
    }

    protected function validateDesignPresentForBuilder(Validator $validator): void
    {
        $team = $this->route('current_team');

        abort_unless($team instanceof Team, 404);

        if ($team->email_editor !== EmailEditor::Builder) {
            return;
        }

        if (blank($this->input('design'))) {
            $validator->errors()->add('design', __('The block layout is missing.'));
        }
    }
}
