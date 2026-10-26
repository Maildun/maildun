<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;

class BulkContactTagsRequest extends BulkContactRequest
{
    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'action' => ['required', 'in:add,remove'],
            'tags' => ['required', 'array', 'min:1', 'max:20'],
            'tags.*' => ['required', 'string', 'max:50'],
        ];
    }
}
