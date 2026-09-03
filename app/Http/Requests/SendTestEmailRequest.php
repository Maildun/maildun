<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class SendTestEmailRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('sendTest', $this->route('email'));
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'to' => ['required', 'string', 'email', 'max:255'],
        ];
    }
}
