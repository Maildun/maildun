<?php

namespace App\Actions\Audiences;

use App\Enums\AudienceAttributeType;
use App\Enums\SegmentRuleField;
use App\Enums\SegmentRuleOperator;
use App\Enums\SubscriberLanguage;
use App\Enums\SubscriberSource;
use App\Models\Audience;
use App\Models\AudienceAttribute;
use App\Models\Subscriber;
use DateTimeImmutable;
use Illuminate\Support\Str;

class ValidateSegmentRules
{
    /**
     * Check each rule's operator against its field and its value against the
     * audience, beyond what the basic enum rules can express.
     *
     * @param  array<int|string, mixed>  $rules
     * @return array<string, string> Error messages keyed by `rules.{index}.{key}`.
     */
    public function handle(Audience $audience, array $rules): array
    {
        $errors = [];

        foreach ($rules as $index => $rule) {
            $rule = is_array($rule) ? $rule : [];
            $field = SegmentRuleField::tryFrom($this->text($rule['field'] ?? null));
            $operator = SegmentRuleOperator::tryFrom($this->text($rule['operator'] ?? null));
            $value = $this->text($rule['value'] ?? null);

            if (! $field || ! $operator || ! in_array($operator, $field->operators(), true)) {
                $errors["rules.{$index}.operator"] = 'The selected operator is not valid for this field.';

                continue;
            }

            if ($field === SegmentRuleField::Attribute) {
                $this->validateAttributeRule($audience, $rule, $operator, $value, "rules.{$index}", $errors);

                continue;
            }

            if ($field === SegmentRuleField::Status && ! in_array($value, Subscriber::MEMBERSHIP_STATUSES, true)) {
                $errors["rules.{$index}.value"] = 'The selected subscriber status is invalid.';
            }

            if ($field === SegmentRuleField::Source && ! SubscriberSource::tryFrom($value)) {
                $errors["rules.{$index}.value"] = 'The selected subscriber source is invalid.';
            }

            if ($field === SegmentRuleField::Language && ! SubscriberLanguage::tryFrom($value)) {
                $errors["rules.{$index}.value"] = 'The selected language is invalid.';
            }

            if ($field === SegmentRuleField::SubscribeForm && ! $audience->subscribeForms()->withTrashed()->where('uuid', $value)->exists()) {
                $errors["rules.{$index}.value"] = 'The selected subscribe form is invalid.';
            }

            if ($field === SegmentRuleField::Tag && ! (Str::isUuid((string) $value) && $audience->team->tags()->where('uuid', Str::lower((string) $value))->exists())) {
                $errors["rules.{$index}.value"] = 'The selected tag is invalid.';
            }

            if ($field === SegmentRuleField::SubscribedAt && ! $this->isCalendarDate($value)) {
                $errors["rules.{$index}.value"] = 'The date must be in YYYY-MM-DD format.';
            }
        }

        return $errors;
    }

    /**
     * The rule part as text, or '' when a hand-crafted request sends a list or
     * object, so it fails validation instead of throwing on the string cast.
     */
    private function text(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }

    /**
     * A custom field rule needs a field of this audience, an operator its type
     * accepts and a value of that type.
     *
     * @param  array<mixed>  $rule
     * @param  array<string, string>  $errors
     */
    private function validateAttributeRule(Audience $audience, array $rule, SegmentRuleOperator $operator, mixed $value, string $key, array &$errors): void
    {
        $attributeUuid = $rule['attribute'] ?? null;
        $attribute = is_string($attributeUuid) && Str::isUuid($attributeUuid)
            ? $audience->audienceAttributes()->where('uuid', Str::lower($attributeUuid))->first()
            : null;

        if (! $attribute instanceof AudienceAttribute) {
            $errors["{$key}.field"] = 'The selected custom field is invalid.';

            return;
        }

        if (! in_array($operator, $attribute->type->segmentOperators(), true)) {
            $errors["{$key}.operator"] = 'The selected operator is not valid for this field.';

            return;
        }

        if ($attribute->type === AudienceAttributeType::Number && ! is_numeric($value)) {
            $errors["{$key}.value"] = 'The value must be a number.';
        }

        if ($attribute->type === AudienceAttributeType::Date && ! $this->isCalendarDate($value)) {
            $errors["{$key}.value"] = 'The date must be in YYYY-MM-DD format.';
        }
    }

    /**
     * Whether the value is a real date written exactly as YYYY-MM-DD. Looser
     * inputs such as "1 month ago" or "04/20/2026" parse, but compare as text
     * against stored timestamps and drift or match the wrong subscribers.
     */
    private function isCalendarDate(mixed $value): bool
    {
        if (! is_string($value)) {
            return false;
        }

        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $date !== false && $date->format('Y-m-d') === $value;
    }
}
