<?php

namespace App\Enums;

enum AudienceAttributeType: string
{
    case Text = 'text';
    case Number = 'number';
    case Date = 'date';

    public function label(): string
    {
        return match ($this) {
            self::Text => 'Text',
            self::Number => 'Number',
            self::Date => 'Date',
        };
    }

    /**
     * The operators a segment rule on a field of this type accepts.
     *
     * @return list<SegmentRuleOperator>
     */
    public function segmentOperators(): array
    {
        return match ($this) {
            self::Text => [
                SegmentRuleOperator::Equals,
                SegmentRuleOperator::NotEquals,
                SegmentRuleOperator::Contains,
                SegmentRuleOperator::DoesNotContain,
            ],
            self::Number => [
                SegmentRuleOperator::Equals,
                SegmentRuleOperator::NotEquals,
                SegmentRuleOperator::GreaterThan,
                SegmentRuleOperator::LessThan,
            ],
            self::Date => [
                SegmentRuleOperator::Before,
                SegmentRuleOperator::After,
            ],
        };
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $type) => [
            'value' => $type->value,
            'label' => $type->label(),
        ], self::cases());
    }
}
