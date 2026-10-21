<?php

namespace App\Console\Commands;

use App\Actions\Audiences\SyncSegmentSubscribers;
use App\Models\Segment;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('segments:sync')]
#[Description('Recompute which subscribers match each dynamic segment\'s rules')]
class SyncSegmentsCommand extends Command
{
    /**
     * Sync every segment. A segment that fails is reported and skipped, so
     * one bad segment cannot stop the segments after it from ever syncing.
     */
    public function handle(SyncSegmentSubscribers $syncSegmentSubscribers): int
    {
        $failed = 0;

        Segment::query()->with('audience')->chunkById(100, function ($segments) use ($syncSegmentSubscribers, &$failed): void {
            foreach ($segments as $segment) {
                try {
                    $syncSegmentSubscribers->handle($segment);
                } catch (Throwable $exception) {
                    report($exception);
                    $failed++;
                    $this->error("Segment {$segment->uuid} failed to sync: {$exception->getMessage()}");
                }
            }
        });

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
