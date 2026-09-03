<?php

namespace App\Console\Commands;

use App\Actions\Audiences\SyncSegmentSubscribers;
use App\Models\Segment;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('segments:sync')]
#[Description('Recompute which subscribers match each dynamic segment\'s rules')]
class SyncSegmentsCommand extends Command
{
    public function handle(SyncSegmentSubscribers $syncSegmentSubscribers): int
    {
        Segment::query()->with('audience')->chunkById(100, function ($segments) use ($syncSegmentSubscribers): void {
            foreach ($segments as $segment) {
                $syncSegmentSubscribers->handle($segment);
            }
        });

        return self::SUCCESS;
    }
}
