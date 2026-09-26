<?php

namespace App\Console\Commands;

use App\Actions\Emails\ScheduleEmailSend;
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
    public function handle(ScheduleEmailSend $schedules, StartEmailSend $startEmailSend): int
    {
        $started = 0;
        $failed = 0;

        Email::query()
            ->where('status', EmailStatus::Draft)
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '<=', now())
            ->orderBy('scheduled_at')
            ->each(function (Email $email) use ($schedules, $startEmailSend, &$started, &$failed): void {
                if (! $schedules->claimDue($email)) {
                    return;
                }

                try {
                    $startEmailSend->handle($email);
                    $started++;
                } catch (ValidationException $exception) {
                    $this->recordFailure($email, (string) collect($exception->errors())->flatten()->first());
                    $failed++;
                } catch (Throwable $exception) {
                    report($exception);
                    $this->recordFailure($email, __('The campaign could not be started. Try sending it again.'));
                    $failed++;
                }
            });

        $this->components->info("Started {$started} scheduled campaigns; {$failed} could not start.");

        return self::SUCCESS;
    }

    /**
     * Keep why a scheduled send did not start on the draft, so the author sees
     * it in the setup hub instead of the campaign silently staying a draft.
     */
    private function recordFailure(Email $email, string $reason): void
    {
        Email::query()->whereKey($email->id)->update(['schedule_error' => $reason]);

        Log::warning('A scheduled campaign could not be started.', [
            'email_id' => $email->id,
            'team_id' => $email->team_id,
            'reason' => $reason,
        ]);
    }
}
