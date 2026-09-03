<?php

namespace App\Http\Requests;

use App\Enums\AudienceAttributeType;
use App\Enums\SubscribeFormFieldMode;
use App\Models\AudienceAttribute;
use App\Models\SubscribeForm;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class PublicSubscribeRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->has('email')) {
            $this->merge(['email' => Str::lower($this->string('email')->trim()->toString())]);
        }
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        $subscribeForm = $this->route('subscribeForm');

        abort_unless($subscribeForm instanceof SubscribeForm, 404);

        $attributes = $subscribeForm->audience->audienceAttributes()
            ->orderBy('position')
            ->get();
        $rules = [
            'email' => ['required', 'string', 'email', 'max:255'],
            'first_name' => [
                Rule::requiredIf($subscribeForm->audience->first_name_mode === SubscribeFormFieldMode::Required),
                Rule::prohibitedIf($subscribeForm->audience->first_name_mode === SubscribeFormFieldMode::Hidden),
                'nullable',
                'string',
                'max:255',
            ],
            'last_name' => [
                Rule::requiredIf($subscribeForm->audience->last_name_mode === SubscribeFormFieldMode::Required),
                Rule::prohibitedIf($subscribeForm->audience->last_name_mode === SubscribeFormFieldMode::Hidden),
                'nullable',
                'string',
                'max:255',
            ],
            'consent' => ['accepted'],
            'website' => ['nullable', 'string', 'max:0'],
        ];

        if ($attributes->isNotEmpty()) {
            $rules['attributes'] = [
                $attributes->contains(fn (AudienceAttribute $attribute): bool => $attribute->required)
                    ? 'required'
                    : 'nullable',
                'array:'.$attributes->pluck('key')->implode(','),
            ];

            foreach ($attributes as $attribute) {
                $rules['attributes.'.$attribute->key] = [
                    $attribute->required ? 'required' : 'nullable',
                    ...match ($attribute->type) {
                        AudienceAttributeType::Text => ['string', 'max:255'],
                        AudienceAttributeType::Number => ['numeric'],
                        AudienceAttributeType::Date => ['date'],
                    },
                ];
            }
        } else {
            $rules['attributes'] = ['nullable', 'array'];
        }

        return $rules;
    }
}
