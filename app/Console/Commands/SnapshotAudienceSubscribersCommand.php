<?php

namespace App\Console\Commands;

use App\Actions\Audiences\RecordAudienceSubscriberSnapshots;
use App\Models\Audience;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('audiences:snapshot-subscribers')]
#[Description('Record each audience\'s member counts for finished days that have no snapshot yet')]
class SnapshotAudienceSubscribersCommand extends Command
{
    /**
     * Snapshot every audience. One that fails is reported and skipped, so it
     * cannot stop the audiences after it from being recorded.
     */
    public function handle(RecordAudienceSubscriberSnapshots $recordSnapshots): int
    {
        $failed = 0;

        Audience::query()->chunkById(100, function ($audiences) use ($recordSnapshots, &$failed): void {
            foreach ($audiences as $audience) {
                try {
                    $recordSnapshots->handle($audience);
                } catch (Throwable $exception) {
                    report($exception);
                    $failed++;
                    $this->error("Audience {$audience->uuid} failed to snapshot: {$exception->getMessage()}");
                }
            }
        });

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
