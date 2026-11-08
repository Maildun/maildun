<?php

namespace App\Http\Requests;

use App\Actions\Audiences\ValidateSegmentRules;
use App\Enums\AudienceAttributeType;
use App\Enums\SegmentRuleField;
use App\Enums\SegmentRuleOperator;
use App\Models\Audience;
use App\Models\AudienceAttribute;
use App\Models\AudienceAttributeMove;
use App\Models\Segment;
use App\Models\Subscriber;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Collection as SupportCollection;
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
     * A key still being moved off by a renamed or deleted field cannot be
     * taken yet. A field cannot become a Number or Date while members still
     * hold values that do not fit that type, or profiles and merge tags would
     * break, nor change type while a segment has a rule the new type cannot
     * evaluate.
     *
     * @return array<int, Closure(Validator): void>
     */
    public function after(ValidateSegmentRules $validateSegmentRules): array
    {
        return [
            function (Validator $validator): void {
                $audience = $this->route('audience');
                $attribute = $this->route('audience_attribute');
                $key = $this->string('key')->toString();

                if (! $audience instanceof Audience
                    || $validator->errors()->has('key')
                    || ($attribute instanceof AudienceAttribute && $attribute->key === $key)) {
                    return;
                }

                $held = AudienceAttributeMove::query()
                    ->holdingKey($audience->id, $key, $attribute instanceof AudienceAttribute ? $attribute : null)
                    ->exists();

                if ($held) {
                    $validator->errors()->add('key', __('Values of a renamed or deleted field are still being moved off this key. Try again in a few minutes.'));
                }
            },
            function (Validator $validator) use ($validateSegmentRules): void {
                $attribute = $this->route('audience_attribute');
                $type = AudienceAttributeType::tryFrom($this->string('type')->toString());

                if (! $attribute instanceof AudienceAttribute
                    || $validator->errors()->isNotEmpty()
                    || $type === null
                    || $type === $attribute->type) {
                    return;
                }

                $segments = $this->segmentsWithRulesTheTypeCannotEvaluate($validateSegmentRules, $attribute, $type);

                if ($segments->isNotEmpty()) {
                    $validator->errors()->add('type', __('These segments have rules a :type field cannot evaluate: :segments. Change those rules before changing the type.', [
                        'type' => Str::lower($type->name),
                        'segments' => $segments->join(', '),
                    ]));

                    return;
                }

                if ($type === AudienceAttributeType::Text) {
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

    /**
     * Names of the audience segments with a rule on this field whose operator
     * or value the new type rejects. Left alone, such a rule matches nobody.
     *
     * @return SupportCollection<int, string>
     */
    private function segmentsWithRulesTheTypeCannotEvaluate(ValidateSegmentRules $validateSegmentRules, AudienceAttribute $attribute, AudienceAttributeType $type): SupportCollection
    {
        return Segment::query()
            ->where('audience_id', $attribute->audience_id)
            ->orderBy('name')
            ->get(['id', 'name', 'rules'])
            ->filter(fn (Segment $segment): bool => collect($segment->rules)->contains(
                fn (array $rule): bool => $rule['field'] === SegmentRuleField::Attribute->value
                    && Str::lower($rule['attribute'] ?? '') === Str::lower($attribute->uuid)
                    && $validateSegmentRules->attributeRuleMismatch($type, SegmentRuleOperator::tryFrom($rule['operator']), $rule['value']) !== null,
            ))
            ->pluck('name')
            ->values();
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
