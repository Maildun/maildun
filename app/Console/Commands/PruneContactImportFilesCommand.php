<?php

namespace App\Console\Commands;

use App\Enums\ContactImportStatus;
use App\Models\ContactImport;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Storage;

#[Signature('contacts:prune-imports {--draft-days=7 : Delete drafts nobody started for this many days} {--failed-days=30 : Delete the uploaded file of imports that failed this many days ago}')]
#[Description('Delete uploaded contact lists of abandoned draft and failed imports')]
class PruneContactImportFilesCommand extends Command
{
    /**
     * Uploaded CSVs are personal data. Completed and cancelled imports already
     * delete theirs; this removes the ones nobody will use any more.
     *
     * An abandoned draft has imported nothing, so the whole draft goes. A
     * failed import keeps its row as history (its counts and errors), and only
     * its file goes, which also hides its Retry button.
     */
    public function handle(): int
    {
        $draftDays = max(1, (int) $this->option('draft-days'));
        $failedDays = max(1, (int) $this->option('failed-days'));
        $deletedDrafts = 0;
        $prunedFailed = 0;

        ContactImport::query()
            ->where('status', ContactImportStatus::Draft)
            ->where('updated_at', '<=', now()->subDays($draftDays))
            ->chunkById(100, function (Collection $contactImports) use (&$deletedDrafts): void {
                foreach ($contactImports as $contactImport) {
                    Storage::disk($contactImport->disk)->delete($contactImport->path);
                    $contactImport->delete();
                    $deletedDrafts++;
                }
            });

        ContactImport::query()
            ->where('status', ContactImportStatus::Failed)
            ->where('completed_at', '<=', now()->subDays($failedDays))
            ->chunkById(100, function (Collection $contactImports) use (&$prunedFailed): void {
                foreach ($contactImports as $contactImport) {
                    $files = Storage::disk($contactImport->disk);

                    if ($files->exists($contactImport->path)) {
                        $files->delete($contactImport->path);
                        $prunedFailed++;
                    }
                }
            });

        $this->components->info("Deleted {$deletedDrafts} abandoned import draft(s) and the files of {$prunedFailed} failed import(s).");

        return self::SUCCESS;
    }
}
