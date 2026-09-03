<?php

namespace App\Http\Requests\Api;

use App\Enums\AudienceAttributeType;
use App\Models\Audience;
use App\Models\Team;
use App\Models\TeamApiKey;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class SubscribeSubscriberRequest extends FormRequest
{
    private ?Audience $resolvedAudience = null;

    public function authorize(): bool
    {
        return $this->attributes->get('teamApiKey') instanceof TeamApiKey;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('email')) {
            $this->merge(['email' => Str::lower($this->string('email')->trim()->toString())]);
        }
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        $attributes = $this->audience()->audienceAttributes()->orderBy('position')->get();

        $rules = [
            'email' => ['required', 'string', 'email', 'max:255'],
            'first_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
            'consent_text' => ['nullable', 'string', 'max:1000'],
        ];

        if ($attributes->isEmpty()) {
            $rules['attributes'] = ['nullable', 'array'];

            return $rules;
        }

        $rules['attributes'] = [
            $attributes->contains(fn ($attribute): bool => $attribute->required) ? 'required' : 'nullable',
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

        return $rules;
    }

    public function audience(): Audience
    {
        if ($this->resolvedAudience instanceof Audience) {
            return $this->resolvedAudience;
        }

        $team = $this->attributes->get('team');
        abort_unless($team instanceof Team, 401);

        $this->resolvedAudience = $team->audiences()
            ->where('uuid', (string) $this->route('audience'))
            ->firstOrFail();

        return $this->resolvedAudience;
    }
}
