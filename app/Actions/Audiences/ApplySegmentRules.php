<?php

namespace App\Actions\Audiences;

use App\Concerns\SearchesText;
use App\Enums\AudienceAttributeType;
use App\Enums\SegmentMatchType;
use App\Enums\SegmentRuleField;
use App\Enums\SegmentRuleOperator;
use App\Models\Audience;
use App\Models\AudienceAttribute;
use App\Models\Subscriber;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class ApplySegmentRules
{
    use SearchesText;

    /**
     * @param  Builder<Subscriber>  $query
     * @param  list<array{field: string, operator: string, value: string, attribute?: string}>  $rules
     * @return Builder<Subscriber>
     */
    public function handle(Audience $audience, Builder $query, array $rules, SegmentMatchType $matchType): Builder
    {
        $formIds = $audience->subscribeForms()
            ->withTrashed()
            ->whereIn('uuid', collect($rules)->where('field', SegmentRuleField::SubscribeForm->value)->pluck('value')->map(fn (mixed $uuid): string => Str::lower((string) $uuid)))
            ->pluck('id', 'uuid');
        $attributes = $audience->audienceAttributes()
            ->whereIn('uuid', collect($rules)->where('field', SegmentRuleField::Attribute->value)->pluck('attribute')->map(fn (mixed $uuid): string => Str::lower((string) $uuid)))
            ->get()
            ->keyBy(fn (AudienceAttribute $attribute): string => Str::lower($attribute->uuid));

        return $query->where(function (Builder $rulesQuery) use ($rules, $matchType, $formIds, $attributes): void {
            foreach ($rules as $index => $rule) {
                $method = $matchType === SegmentMatchType::Any && $index > 0 ? 'orWhere' : 'where';

                $rulesQuery->{$method}(function (Builder $ruleQuery) use ($rule, $formIds, $attributes): void {
                    $field = SegmentRuleField::from($rule['field']);
                    $operator = SegmentRuleOperator::from($rule['operator']);
                    $value = $field === SegmentRuleField::SubscribeForm
                        ? $formIds->get(Str::lower($rule['value']))
                        : $rule['value'];

                    if ($field === SegmentRuleField::SubscribeForm && $value === null) {
                        $this->applyMissingTargetRule($ruleQuery, $operator);

                        return;
                    }

                    if ($field === SegmentRuleField::Attribute) {
                        $attribute = $attributes->get(Str::lower((string) ($rule['attribute'] ?? '')));

                        $attribute instanceof AudienceAttribute
                            ? $this->applyAttributeRule($ruleQuery, $attribute, $operator, (string) $value)
                            : $this->applyMissingTargetRule($ruleQuery, $operator);

                        return;
                    }

                    if ($field === SegmentRuleField::Status) {
                        $this->applyStatusRule($ruleQuery, $operator, (string) $value);

                        return;
                    }

                    if ($field === SegmentRuleField::Tag) {
                        $this->applyTagRule($ruleQuery, $operator, Str::lower((string) $value));

                        return;
                    }

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
            $this->applyTextRule($query, $column, [], $operator, (string) $value);

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
     * Status rules use the same membership states as the audience filter, so
     * pending members are neither "subscribed" nor left out of "is not
     * subscribed".
     *
     * @param  Builder<Subscriber>  $query
     */
    private function applyStatusRule(Builder $query, SegmentRuleOperator $operator, string $status): void
    {
        $operator === SegmentRuleOperator::NotEquals
            ? $query->whereNot(fn (Builder $statusQuery) => $statusQuery->withMembershipStatus($status))
            : $query->withMembershipStatus($status);
    }

    /**
     * Tags belong to the contact, so a member has a tag when their contact
     * does. A deleted tag is on nobody: "is" matches no one, "is not" everyone.
     *
     * @param  Builder<Subscriber>  $query
     */
    private function applyTagRule(Builder $query, SegmentRuleOperator $operator, string $tagUuid): void
    {
        $hasTag = fn (Builder $tagQuery) => $tagQuery->where('tags.uuid', $tagUuid);

        $operator === SegmentRuleOperator::NotEquals
            ? $query->whereDoesntHave('contact.tags', $hasTag)
            : $query->whereHas('contact.tags', $hasTag);
    }

    /**
     * Case-insensitive text matching on a column or JSON expression. The
     * negative operators also match members with no value.
     *
     * @param  Builder<Subscriber>  $query
     * @param  literal-string  $expression
     * @param  list<string>  $expressionBindings  Bindings for each "?" in the expression.
     */
    private function applyTextRule(Builder $query, string $expression, array $expressionBindings, SegmentRuleOperator $operator, string $value): void
    {
        $normalizedValue = Str::lower($value);
        $pattern = $this->containsPattern($normalizedValue);

        match ($operator) {
            SegmentRuleOperator::Equals => $query->whereRaw("LOWER({$expression}) = ?", [...$expressionBindings, $normalizedValue]),
            SegmentRuleOperator::NotEquals => $query->where(fn (Builder $textQuery) => $textQuery
                ->whereRaw("LOWER({$expression}) != ?", [...$expressionBindings, $normalizedValue])
                ->orWhereRaw("{$expression} IS NULL", $expressionBindings)),
            SegmentRuleOperator::Contains => $query->whereRaw("LOWER({$expression}) LIKE ? ESCAPE '!'", [...$expressionBindings, $pattern]),
            SegmentRuleOperator::DoesNotContain => $query->where(fn (Builder $textQuery) => $textQuery
                ->whereRaw("LOWER({$expression}) NOT LIKE ? ESCAPE '!'", [...$expressionBindings, $pattern])
                ->orWhereRaw("{$expression} IS NULL", $expressionBindings)),
            default => null,
        };
    }

    /**
     * A rule on a custom field, read from subscribers.attribute_values. Number
     * fields compare numerically and skip values that are not numbers; date
     * fields compare the YYYY-MM-DD day the value starts with. The field key
     * is bound rather than written into the SQL.
     *
     * @param  Builder<Subscriber>  $query
     */
    private function applyAttributeRule(Builder $query, AudienceAttribute $attribute, SegmentRuleOperator $operator, string $value): void
    {
        $isPostgres = $query->getModel()->getConnection()->getDriverName() === 'pgsql';
        $json = $isPostgres
            ? '("subscribers"."attribute_values" ->> CAST(? AS text))'
            : 'json_extract("subscribers"."attribute_values", ?)';
        $path = $isPostgres ? $attribute->key : '$."'.$attribute->key.'"';

        if ($attribute->type === AudienceAttributeType::Text) {
            $this->applyTextRule($query, $json, [$path], $operator, $value);

            return;
        }

        if ($attribute->type === AudienceAttributeType::Date) {
            $bindings = [$path, $this->calendarDate($value)];

            match ($operator) {
                SegmentRuleOperator::Before => $query->whereRaw("SUBSTR({$json}, 1, 10) < ?", $bindings),
                SegmentRuleOperator::After => $query->whereRaw("SUBSTR({$json}, 1, 10) > ?", $bindings),
                default => null,
            };

            return;
        }

        // The value as a number, or NULL when it is not one, so a stray text
        // value never fails the cast on Postgres. Bindings arrive as text,
        // which SQLite would otherwise compare as text.
        [$number, $bound, $uses] = $isPostgres
            ? ["(CASE WHEN {$json} ~ '^[[:space:]]*-?[0-9]+([.][0-9]+)?[[:space:]]*\$' THEN CAST({$json} AS numeric) END)", 'CAST(? AS numeric)', 2]
            : ["(CASE WHEN typeof({$json}) IN ('integer', 'real') THEN {$json} WHEN TRIM({$json}) != '' AND TRIM({$json}) NOT GLOB '*[^0-9.-]*' THEN CAST(TRIM({$json}) AS REAL) END)", 'CAST(? AS REAL)', 5];
        $paths = array_fill(0, $uses, $path);
        $bindings = [...$paths, (float) $value];

        match ($operator) {
            SegmentRuleOperator::Equals => $query->whereRaw("{$number} = {$bound}", $bindings),
            SegmentRuleOperator::NotEquals => $query->where(fn (Builder $numberQuery) => $numberQuery
                ->whereRaw("{$number} != {$bound}", $bindings)
                ->orWhereRaw("{$number} IS NULL", $paths)),
            SegmentRuleOperator::GreaterThan => $query->whereRaw("{$number} > {$bound}", $bindings),
            SegmentRuleOperator::LessThan => $query->whereRaw("{$number} < {$bound}", $bindings),
            default => null,
        };
    }

    /**
     * A rule whose form or custom field is gone matches nobody for a positive
     * operator and everyone for "is not" / "does not contain", instead of
     * comparing with NULL.
     *
     * @param  Builder<Subscriber>  $query
     */
    private function applyMissingTargetRule(Builder $query, SegmentRuleOperator $operator): void
    {
        if (! in_array($operator, [SegmentRuleOperator::NotEquals, SegmentRuleOperator::DoesNotContain], true)) {
            $query->whereRaw('1 = 0');
        }
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
