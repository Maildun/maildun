<?php

namespace App\Http\Requests;

use App\Enums\AudienceAttributeType;
use App\Models\Audience;
use App\Models\AudienceAttribute;
use App\Models\Subscriber;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

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

    /**
     * A field cannot become a Number or Date while members still hold values
     * that do not fit that type, or profiles and merge tags would break.
     *
     * @return array<int, Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $attribute = $this->route('audience_attribute');
                $type = AudienceAttributeType::tryFrom($this->string('type')->toString());

                if (! $attribute instanceof AudienceAttribute
                    || $validator->errors()->isNotEmpty()
                    || $type === null
                    || $type === $attribute->type
                    || $type === AudienceAttributeType::Text) {
                    return;
                }

                $hasMismatch = false;

                Subscriber::query()
                    ->where('audience_id', $attribute->audience_id)
                    ->whereJsonContainsKey('attribute_values->'.$attribute->key)
                    ->select(['id', 'attribute_values'])
                    ->chunkById(500, function (Collection $subscribers) use ($attribute, $type, &$hasMismatch): bool {
                        $hasMismatch = $subscribers->contains(
                            fn (Subscriber $subscriber): bool => ! self::valueFitsType($subscriber->attribute_values[$attribute->key] ?? null, $type),
                        );

                        return ! $hasMismatch;
                    });

                if ($hasMismatch) {
                    $validator->errors()->add('type', __('Some subscribers have values that are not a valid :type. Fix those values before changing the type.', [
                        'type' => Str::lower($type->name),
                    ]));
                }
            },
        ];
    }

    private static function valueFitsType(mixed $value, AudienceAttributeType $type): bool
    {
        if ($value === null || $value === '') {
            return true;
        }

        return match ($type) {
            AudienceAttributeType::Number => is_numeric($value),
            AudienceAttributeType::Date => is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1,
            AudienceAttributeType::Text => true,
        };
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'key.not_in' => __('That key is reserved for a built-in subscriber field or merge tag.'),
            'key.regex' => __('The key must start with a letter and contain only lowercase letters, numbers, and underscores.'),
            'key.unique' => __('An attribute with that key already exists in this audience.'),
            'name.unique' => __('An attribute with that name already exists in this audience.'),
        ];
    }
}
