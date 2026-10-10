<?php

namespace App\Console\Commands;

use App\Services\InstallationState;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:system-test
    {--skip=* : Check keys to leave out, e.g. --skip=horizon --skip=scheduler}
    {--no-storage-probe : Skip the temporary file write, read, and delete}')]
#[Description('Run the installer system test headless: app key, database, cache, queue, Horizon, storage, and scheduler')]
class SystemTestCommand extends Command
{
    /**
     * The same checks the browser system test page runs, so a deploy script
     * can fail fast on a broken environment. Pending checks (the scheduler on
     * a fresh box) are reported but never fail the run.
     */
    public function handle(InstallationState $installation): int
    {
        /** @var list<string> $skipped */
        $skipped = $this->option('skip');

        $checks = array_values(array_filter(
            $installation->systemChecks(probeStorage: ! $this->option('no-storage-probe')),
            fn (array $check): bool => ! in_array($check['key'], $skipped, true),
        ));

        $this->components->info('Testing '.config('app.name').'.');

        foreach ($checks as $check) {
            $this->components->twoColumnDetail(
                $check['label'].' <fg=gray>('.$check['key'].')</>',
                match ($check['status']) {
                    'ready' => '<fg=green>ready</>',
                    'pending' => '<fg=yellow>pending</>',
                    'failed' => '<fg=red>failed</>',
                },
            );
        }

        $failed = array_filter($checks, fn (array $check): bool => $check['status'] === 'failed');
        $pending = array_filter($checks, fn (array $check): bool => $check['status'] === 'pending');

        if ($pending !== []) {
            $this->components->warn('Not yet verifiable:');
            $this->components->bulletList(array_map(fn (array $check): string => $check['label'].': '.$check['description'], $pending));
        }

        if ($failed !== []) {
            $this->components->error('The system test failed:');
            $this->components->bulletList(array_map(fn (array $check): string => $check['label'].': '.$check['description'], $failed));

            return self::FAILURE;
        }

        $this->components->info('Every check passed.');

        return self::SUCCESS;
    }
}
