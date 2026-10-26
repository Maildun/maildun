<?php

namespace App\Console\Commands;

use App\Enums\ContactImportStatus;
use App\Jobs\ProcessContactImport;
use App\Models\ContactImport;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;

#[Signature('contacts:resume-imports')]
#[Description('Re-dispatch contact imports whose queued job was lost')]
class ResumeContactImportsCommand extends Command
{
    /**
     * A running import saves its checkpoint after every row, so one that has
     * not moved for this long has no live job: even a job waiting out its
     * longest retry backoff (5 minutes) touches the row well within it.
     */
    private const int STALLED_AFTER_MINUTES = 15;

    /**
     * Redis is not durable storage, so a flush, eviction, or queue reset can
     * strand a queued or half-finished import. Re-dispatching is safe: the job
     * resumes from file_offset, WithoutOverlapping keeps a second copy from
     * running alongside a live one, and it does nothing once the import has
     * finished or been cancelled.
     */
    public function handle(): int
    {
        $resumed = 0;

        ContactImport::query()
            ->whereIn('status', [ContactImportStatus::Pending, ContactImportStatus::Processing])
            ->where('updated_at', '<=', now()->subMinutes(self::STALLED_AFTER_MINUTES))
            ->chunkById(100, function (Collection $contactImports) use (&$resumed): void {
                foreach ($contactImports as $contactImport) {
                    // Restart the stall clock so a job still waiting in a long
                    // queue is not dispatched again on every sweep.
                    $contactImport->touch();
                    ProcessContactImport::dispatch($contactImport->id);
                    $resumed++;
                }
            });

        if ($resumed > 0) {
            $this->info(__(':count contact import(s) resumed.', ['count' => $resumed]));
        }

        return self::SUCCESS;
    }
}
