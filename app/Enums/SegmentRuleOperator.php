<?php

namespace App\Enums;

enum SegmentRuleOperator: string
{
    case Equals = 'equals';
    case NotEquals = 'not_equals';
    case Contains = 'contains';
    case DoesNotContain = 'does_not_contain';
    case Before = 'before';
    case After = 'after';
}
