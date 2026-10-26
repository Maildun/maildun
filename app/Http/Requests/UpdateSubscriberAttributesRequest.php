<?php

namespace App\Http\Requests;

use App\Enums\AudienceAttributeType;
use App\Models\Audience;
use App\Models\AudienceAttribute;
use App\Models\Subscriber;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class UpdateSubscriberAttributesRequest extends FormRequest
{
    /** @var Collection<int, AudienceAttribute>|null */
    private ?Collection $audienceAttributes = null;

    public function authorize(): bool
    {
        $subscriber = $this->route('subscriber');

        return $subscriber instanceof Subscriber && Gate::allows('update', $subscriber);
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('attributes')) {
            $this->merge(['attributes' => AudienceAttribute::normalizeDateInput(
                $this->input('attributes'),
                $this->audienceAttributes(),
            )]);
        }
    }

    /**
     * Each custom field is validated by its type, and required fields must
     * stay filled, the same as public forms and the API.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $attributes = $this->audienceAttributes();
        $rules = [
            'attributes' => [
                $attributes->contains('required', true) ? 'required' : 'present',
                'array:'.$attributes->pluck('key')->implode(','),
            ],
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

    /** @return array<string, string> */
    public function attributes(): array
    {
        return $this->audienceAttributes()
            ->mapWithKeys(fn (AudienceAttribute $attribute): array => [
                'attributes.'.$attribute->key => $attribute->name,
            ])
            ->all();
    }

    /** @return Collection<int, AudienceAttribute> */
    public function audienceAttributes(): Collection
    {
        $audience = $this->route('audience');

        abort_unless($audience instanceof Audience, 404);

        return $this->audienceAttributes ??= $audience->audienceAttributes()->orderBy('position')->get();
    }
}
