<?php

namespace App\Http\Requests;

use App\Models\Automation;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class StoreAutomationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('create', [Automation::class, $this->route('current_team')]);
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:255'],
        ];
    }
}
