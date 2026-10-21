<?php

namespace App\Actions\Audiences;

use App\Enums\SegmentMatchType;
use App\Enums\SegmentRuleField;
use App\Enums\SegmentRuleOperator;
use App\Models\Audience;
use App\Models\Subscriber;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class ApplySegmentRules
{
    /**
     * @param  Builder<Subscriber>  $query
     * @param  list<array{field: string, operator: string, value: string}>  $rules
     * @return Builder<Subscriber>
     */
    public function handle(Audience $audience, Builder $query, array $rules, SegmentMatchType $matchType): Builder
    {
        $formIds = $audience->subscribeForms()
            ->withTrashed()
            ->whereIn('uuid', collect($rules)->where('field', SegmentRuleField::SubscribeForm->value)->pluck('value'))
            ->pluck('id', 'uuid');

        return $query->where(function (Builder $rulesQuery) use ($rules, $matchType, $formIds): void {
            foreach ($rules as $index => $rule) {
                $method = $matchType === SegmentMatchType::Any && $index > 0 ? 'orWhere' : 'where';

                $rulesQuery->{$method}(function (Builder $ruleQuery) use ($rule, $formIds): void {
                    $field = SegmentRuleField::from($rule['field']);
                    $operator = SegmentRuleOperator::from($rule['operator']);
                    $value = $field === SegmentRuleField::SubscribeForm
                        ? $formIds->get($rule['value'])
                        : $rule['value'];

                    $this->applyRule($ruleQuery, $field, $operator, $value);
                });
            }
        });
    }

    /** @param Builder<Subscriber> $query */
    private function applyRule(Builder $query, SegmentRuleField $field, SegmentRuleOperator $operator, mixed $value): void
    {
        $column = match ($field) {
            SegmentRuleField::SubscribeForm => 'subscribe_form_id',
            default => $field->value,
        };

        if (in_array($field, [SegmentRuleField::Email, SegmentRuleField::FirstName, SegmentRuleField::LastName], true)) {
            $normalizedValue = Str::lower((string) $value);
            $containsPattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $normalizedValue).'%';

            match ($operator) {
                SegmentRuleOperator::Equals => $query->whereRaw("LOWER({$column}) = ?", [$normalizedValue]),
                SegmentRuleOperator::NotEquals => $query->where(fn (Builder $textQuery) => $textQuery
                    ->whereRaw("LOWER({$column}) != ?", [$normalizedValue])
                    ->orWhereNull($column)),
                SegmentRuleOperator::Contains => $query->whereRaw("LOWER({$column}) LIKE ? ESCAPE '!'", [$containsPattern]),
                SegmentRuleOperator::DoesNotContain => $query->where(fn (Builder $textQuery) => $textQuery
                    ->whereRaw("LOWER({$column}) NOT LIKE ? ESCAPE '!'", [$containsPattern])
                    ->orWhereNull($column)),
                default => null,
            };

            return;
        }

        match ($operator) {
            SegmentRuleOperator::Equals => $query->where($column, $value),
            SegmentRuleOperator::NotEquals => $query->where(fn (Builder $valueQuery) => $valueQuery
                ->where($column, '!=', $value)
                ->orWhereNull($column)),
            SegmentRuleOperator::Before => $query->whereDate($column, '<', $this->calendarDate($value)),
            SegmentRuleOperator::After => $query->whereDate($column, '>', $this->calendarDate($value)),
            default => null,
        };
    }

    /**
     * Normalize a date rule value to YYYY-MM-DD. New rules are validated to that
     * format already; this keeps rules saved before that check comparing as
     * dates instead of as text.
     */
    private function calendarDate(mixed $value): string
    {
        return Carbon::parse((string) $value)->toDateString();
    }
}
