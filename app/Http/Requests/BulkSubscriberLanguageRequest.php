<?php

namespace App\Http\Requests;

use App\Enums\SubscriberLanguage;
use App\Models\Audience;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

class BulkSubscriberLanguageRequest extends BulkSubscriberRequest
{
    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        $audience = $this->route('audience');

        abort_unless($audience instanceof Audience, 404);

        return [
            ...parent::rules(),
            'language' => ['required', Rule::enum(SubscriberLanguage::class), Rule::in($audience->allowed_languages ?? [])],
        ];
    }
}
