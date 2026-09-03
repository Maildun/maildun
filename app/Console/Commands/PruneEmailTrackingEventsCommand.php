<?php

namespace App\Console\Commands;

use App\Models\EmailTrackingEvent;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('emails:prune-tracking {--days= : Override the configured retention period} {--batch= : Override the configured delete batch size}')]
#[Description('Delete processed tracking event payloads after the privacy retention period')]
class PruneEmailTrackingEventsCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $days = $this->integerOption('days', (int) config('tracking.retention.days'));
        $batchSize = max(
            $this->integerOption('batch', (int) config('tracking.retention.batch_size')),
            1,
        );

        if ($days <= 0) {
            $this->components->info('Tracking event pruning is disabled.');

            return self::SUCCESS;
        }

        $cutoff = now()->subDays($days);
        $deleted = 0;

        do {
            $ids = EmailTrackingEvent::query()
                ->whereNotNull('processed_at')
                ->where('occurred_at', '<', $cutoff)
                ->oldest('id')
                ->limit($batchSize)
                ->pluck('id');

            $batchDeleted = $ids->isEmpty()
                ? 0
                : EmailTrackingEvent::query()->whereKey($ids)->delete();
            $deleted += $batchDeleted;
        } while ($batchDeleted === $batchSize);

        $this->components->info("Deleted {$deleted} expired tracking events; campaign aggregates were preserved.");

        return self::SUCCESS;
    }

    private function integerOption(string $name, int $default): int
    {
        $value = $this->option($name);

        return is_numeric($value) ? (int) $value : $default;
    }
}
