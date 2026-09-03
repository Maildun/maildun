<?php

namespace App\Actions\Audiences;

use App\Models\Segment;
use Illuminate\Support\Facades\Date;

class SyncSegmentSubscribers
{
    public function __construct(private ApplySegmentRules $applySegmentRules) {}

    public function handle(Segment $segment): void
    {
        $subscriberIds = $this->applySegmentRules
            ->handle($segment->audience, $segment->audience->subscribers()->getQuery(), $segment->rules, $segment->match_type)
            ->pluck('id');

        $segment->subscribers()->sync($subscriberIds);
        $segment->forceFill(['rules_synced_at' => Date::now()])->save();
    }
}
