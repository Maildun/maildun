<?php

namespace App\Http\Requests;

use App\Enums\AudienceAttributeType;
use App\Models\Audience;
use App\Models\AudienceAttribute;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class SaveAudienceAttributeRequest extends FormRequest
{
    public function authorize(): bool
    {
        $attribute = $this->route('audience_attribute');

        return $attribute instanceof AudienceAttribute
            ? Gate::allows('update', $attribute)
            : Gate::allows('create', [AudienceAttribute::class, $this->route('audience')]);
    }

    protected function prepareForValidation(): void
    {
        $name = $this->string('name')->trim()->toString();
        $key = $this->string('key')->trim()->toString();

        if ($key === '' && $name !== '') {
            $key = AudienceAttribute::keyFromName($name);
        } elseif ($key !== '') {
            $key = AudienceAttribute::keyFromName($key);
        }

        $this->merge([
            'name' => $name !== '' ? $name : null,
            'key' => $key !== '' ? $key : null,
            'required' => $this->boolean('required'),
        ]);
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        $audience = $this->route('audience');
        $attribute = $this->route('audience_attribute');

        abort_unless($audience instanceof Audience, 404);

        return [
            'name' => [
                'required',
                'string',
                'max:50',
                Rule::unique(AudienceAttribute::class)
                    ->where('audience_id', $audience->id)
                    ->ignore($attribute),
            ],
            'key' => [
                'required',
                'string',
                'max:50',
                'regex:/^[a-z][a-z0-9_]*$/',
                Rule::notIn(AudienceAttribute::RESERVED_KEYS),
                Rule::unique(AudienceAttribute::class)
                    ->where('audience_id', $audience->id)
                    ->ignore($attribute),
            ],
            'type' => ['required', Rule::enum(AudienceAttributeType::class)],
            'required' => ['boolean'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'key.not_in' => __('That key is reserved for a built-in subscriber field.'),
            'key.regex' => __('The key must start with a letter and contain only lowercase letters, numbers, and underscores.'),
            'key.unique' => __('An attribute with that key already exists in this audience.'),
            'name.unique' => __('An attribute with that name already exists in this audience.'),
        ];
    }
}
