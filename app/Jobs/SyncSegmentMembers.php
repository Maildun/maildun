<?php

namespace App\Jobs;

use App\Actions\Audiences\SyncSegmentSubscribers;
use App\Models\Segment;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Materializes a new segment's members off the request, so creating a
 * segment over a large audience cannot time out. Until it runs the segment
 * page shows that its rules have not synced yet, and segments:sync picks up
 * any segment whose job was lost.
 */
class SyncSegmentMembers implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [5, 30];

    public int $timeout = 60;

    public int $uniqueFor = 600;

    public function __construct(public int $segmentId) {}

    public function uniqueId(): string
    {
        return 'segment-sync:'.$this->segmentId;
    }

    /**
     * Execute the job.
     */
    public function handle(SyncSegmentSubscribers $syncSegmentSubscribers): void
    {
        $segment = Segment::query()->find($this->segmentId);

        if ($segment === null) {
            return;
        }

        $syncSegmentSubscribers->handle($segment);
    }
}
