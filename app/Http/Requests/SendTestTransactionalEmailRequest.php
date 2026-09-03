<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class SendTestTransactionalEmailRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('sendTest', $this->route('transactionalEmail'));
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'to' => ['required', 'string', 'email', 'max:255'],
            'data' => ['nullable', 'array'],
            'data.*' => ['nullable', 'string', 'max:255'],
        ];
    }
}
