<?php

namespace App\Actions\Audiences;

use App\Enums\SegmentMatchType;
use App\Enums\SegmentRuleField;
use App\Enums\SegmentRuleOperator;
use App\Models\Audience;
use App\Models\Subscriber;
use Illuminate\Database\Eloquent\Builder;
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

            match ($operator) {
                SegmentRuleOperator::Equals => $query->whereRaw("LOWER({$column}) = ?", [$normalizedValue]),
                SegmentRuleOperator::NotEquals => $query->whereRaw("LOWER({$column}) != ?", [$normalizedValue]),
                SegmentRuleOperator::Contains => $query->whereRaw("LOWER({$column}) LIKE ?", ['%'.$normalizedValue.'%']),
                SegmentRuleOperator::DoesNotContain => $query->whereRaw("LOWER({$column}) NOT LIKE ?", ['%'.$normalizedValue.'%']),
                default => null,
            };

            return;
        }

        match ($operator) {
            SegmentRuleOperator::Equals => $query->where($column, $value),
            SegmentRuleOperator::NotEquals => $query->where($column, '!=', $value),
            SegmentRuleOperator::Before => $query->whereDate($column, '<', $value),
            SegmentRuleOperator::After => $query->whereDate($column, '>', $value),
            default => null,
        };
    }
}
