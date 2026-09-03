<?php

namespace App\Console\Commands;

use App\Actions\Emails\RebuildEmailTrackingAggregates;
use App\Models\Email;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('emails:rebuild-tracking {--email= : Rebuild only this campaign UUID}')]
#[Description('Rebuild campaign and link tracking aggregates from delivery records')]
class RebuildEmailTrackingAggregatesCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(RebuildEmailTrackingAggregates $rebuild): int
    {
        $emailUuid = trim((string) $this->option('email'));

        if ($emailUuid !== '') {
            $email = Email::withTrashed()->where('uuid', $emailUuid)->first();

            if (! $email instanceof Email) {
                $this->error(__('Campaign not found.'));

                return self::FAILURE;
            }

            $rebuild->handle($email);
            $this->info(__('Tracking aggregates rebuilt for :campaign.', ['campaign' => $email->name]));

            return self::SUCCESS;
        }

        $rebuilt = 0;

        Email::withTrashed()
            ->select(['id'])
            ->orderBy('id')
            ->chunkById(100, function ($emails) use ($rebuild, &$rebuilt): void {
                foreach ($emails as $email) {
                    $rebuild->handle($email);
                    $rebuilt++;
                }
            });

        $this->info(__(':count campaign tracking aggregate(s) rebuilt.', ['count' => $rebuilt]));

        return self::SUCCESS;
    }
}
