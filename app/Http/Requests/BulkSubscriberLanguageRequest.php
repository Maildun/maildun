<?php

namespace App\Http\Requests;

use App\Enums\SubscriberLanguage;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

class BulkSubscriberLanguageRequest extends BulkSubscriberRequest
{
    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'language' => ['required', Rule::enum(SubscriberLanguage::class)],
        ];
    }
}
