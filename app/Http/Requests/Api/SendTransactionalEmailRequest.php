<?php

namespace App\Http\Requests\Api;

use App\Models\TeamApiKey;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Validator;

class SendTransactionalEmailRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->attributes->get('teamApiKey') instanceof TeamApiKey;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('to')) {
            $this->merge(['to' => Str::lower($this->string('to')->trim()->toString())]);
        }

        if ($this->hasHeader('Idempotency-Key')) {
            $this->merge(['idempotency_key' => $this->header('Idempotency-Key')]);
        }
    }

    /** @return array<string, array<mixed>|string> */
    public function rules(): array
    {
        return [
            'to' => ['required', 'string', 'email', 'max:255'],
            'data' => ['sometimes', 'array', 'max:100'],
            'data.*' => ['nullable'],
            'idempotency_key' => ['sometimes', 'string', 'max:255'],
        ];
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                /** @var array<string, mixed> $data */
                $data = $this->input('data', []);

                foreach ($data as $key => $value) {
                    if (preg_match('/\A[a-zA-Z_][a-zA-Z0-9_]*\z/', (string) $key) !== 1) {
                        $validator->errors()->add('data', __('Variable names may contain only letters, numbers, and underscores, and may not start with a number.'));
                    }

                    if (! is_scalar($value) && $value !== null) {
                        $validator->errors()->add('data.'.$key, __('Variable values must be strings, numbers, booleans, or null.'));
                    }
                }
            },
        ];
    }
}
