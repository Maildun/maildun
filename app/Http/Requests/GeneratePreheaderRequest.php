<?php

namespace App\Http\Requests;

use App\Services\AssistantModels;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

/**
 * Asks the assistant for a preheader. Shares the design assistant's
 * authorization and record lookup, so it works in the same editors.
 */
class GeneratePreheaderRequest extends DesignAssistantRequest
{
    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'design' => ['required', 'array', 'required_array_keys:root'],
            'design.root' => ['array'],
            'model' => ['nullable', 'string', Rule::in(array_column(app(AssistantModels::class)->available(), 'id'))],
            'subject' => ['nullable', 'string', 'max:255'],
        ];
    }
}
