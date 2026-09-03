<?php

namespace App\Enums;

enum SegmentRuleField: string
{
    case Email = 'email';
    case FirstName = 'first_name';
    case LastName = 'last_name';
    case Status = 'status';
    case Source = 'source';
    case SubscribeForm = 'subscribe_form';
    case SubscribedAt = 'subscribed_at';

    /**
     * @return array<SegmentRuleOperator>
     */
    public function operators(): array
    {
        return match ($this) {
            self::Email, self::FirstName, self::LastName => [
                SegmentRuleOperator::Equals,
                SegmentRuleOperator::NotEquals,
                SegmentRuleOperator::Contains,
                SegmentRuleOperator::DoesNotContain,
            ],
            self::Status, self::Source, self::SubscribeForm => [
                SegmentRuleOperator::Equals,
                SegmentRuleOperator::NotEquals,
            ],
            self::SubscribedAt => [
                SegmentRuleOperator::Before,
                SegmentRuleOperator::After,
            ],
        };
    }
}
