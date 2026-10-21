<?php

namespace App\Http\Requests;

use App\Actions\Companies\ValidateCompanyInput;
use App\Models\Company;
use App\Models\Team;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
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
        $this->merge(app(ValidateCompanyInput::class)->normalize($this->input('name'), $this->array('domains')));
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(ValidateCompanyInput $validateCompanyInput): array
    {
        return $validateCompanyInput->rules();
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return app(ValidateCompanyInput::class)->messages();
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return app(ValidateCompanyInput::class)->attributes();
    }

    /** @return list<Closure(Validator): void> */
    public function after(ValidateCompanyInput $validateCompanyInput): array
    {
        return [
            function (Validator $validator) use ($validateCompanyInput): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $team = $this->route('current_team');
                $company = $this->route('company');

                abort_unless($team instanceof Team, 404);

                $errors = $validateCompanyInput->errors(
                    $team,
                    $company instanceof Company ? $company : null,
                    $this->string('name')->toString(),
                    array_values($this->array('domains')),
                );

                foreach ($errors as $key => $messages) {
                    foreach ($messages as $message) {
                        $validator->errors()->add($key, $message);
                    }
                }
            },
        ];
    }
}
