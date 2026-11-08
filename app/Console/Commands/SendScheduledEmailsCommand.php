<?php

namespace App\Console\Commands;

use App\Actions\Emails\StartEmailSend;
use App\Enums\EmailStatus;
use App\Models\Email;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

#[Signature('emails:send-scheduled')]
#[Description('Start campaigns whose scheduled send time has come')]
class SendScheduledEmailsCommand extends Command
{
    public function handle(StartEmailSend $startEmailSend): int
    {
        $started = 0;
        $failed = 0;

        // Starting a draft takes it out of this query, so page by id: offset
        // paging would skip every draft that moves up into a finished page.
        Email::query()
            ->where('status', EmailStatus::Draft)
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '<=', now())
            ->eachById(function (Email $email) use ($startEmailSend, &$started, &$failed): void {
                try {
                    if ($startEmailSend->startScheduled($email) !== null) {
                        $started++;
                    }
                } catch (ValidationException $exception) {
                    // The author has to fix something before it can send, so
                    // retrying every minute would only repeat the same error.
                    $this->recordFailure($email, (string) collect($exception->errors())->flatten()->first(), unschedule: true);
                    $failed++;
                } catch (Throwable $exception) {
                    // A start that failed inside its transaction rolled back
                    // with the draft still scheduled, so the next run tries
                    // again. One that failed queueing its batch already left
                    // Draft and is not picked up twice.
                    report($exception);
                    $this->recordFailure($email, __('The campaign could not be started. Try sending it again.'), unschedule: false);
                    $failed++;
                }
            }, 100);

        $this->components->info("Started {$started} scheduled campaigns; {$failed} could not start.");

        return self::SUCCESS;
    }

    /**
     * Keep why a scheduled send did not start on the draft, so the author sees
     * it in the setup hub instead of the campaign silently staying a draft.
     */
    private function recordFailure(Email $email, string $reason, bool $unschedule): void
    {
        Email::query()->whereKey($email->id)->update(['schedule_error' => $reason]);

        if ($unschedule) {
            Email::query()
                ->whereKey($email->id)
                ->where('status', EmailStatus::Draft)
                ->update(['scheduled_at' => null]);
        }

        Log::warning('A scheduled campaign could not be started.', [
            'email_id' => $email->id,
            'team_id' => $email->team_id,
            'reason' => $reason,
        ]);
    }
}
