<?php

namespace App\Actions\Audiences;

use App\Enums\SegmentRuleField;
use App\Enums\SegmentRuleOperator;
use App\Enums\SubscriberLanguage;
use App\Enums\SubscriberSource;
use App\Enums\SubscriberStatus;
use App\Models\Audience;
use DateTimeImmutable;

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
            $field = SegmentRuleField::tryFrom((string) ($rule['field'] ?? ''));
            $operator = SegmentRuleOperator::tryFrom((string) ($rule['operator'] ?? ''));
            $value = $rule['value'] ?? null;

            if (! $field || ! $operator || ! in_array($operator, $field->operators(), true)) {
                $errors["rules.{$index}.operator"] = 'The selected operator is not valid for this field.';

                continue;
            }

            if ($field === SegmentRuleField::Status && ! SubscriberStatus::tryFrom((string) $value)) {
                $errors["rules.{$index}.value"] = 'The selected subscriber status is invalid.';
            }

            if ($field === SegmentRuleField::Source && ! SubscriberSource::tryFrom((string) $value)) {
                $errors["rules.{$index}.value"] = 'The selected subscriber source is invalid.';
            }

            if ($field === SegmentRuleField::Language && ! SubscriberLanguage::tryFrom((string) $value)) {
                $errors["rules.{$index}.value"] = 'The selected language is invalid.';
            }

            if ($field === SegmentRuleField::SubscribeForm && ! $audience->subscribeForms()->withTrashed()->where('uuid', $value)->exists()) {
                $errors["rules.{$index}.value"] = 'The selected subscribe form is invalid.';
            }

            if ($field === SegmentRuleField::SubscribedAt && ! $this->isCalendarDate($value)) {
                $errors["rules.{$index}.value"] = 'The date must be in YYYY-MM-DD format.';
            }
        }

        return $errors;
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
