<?php

namespace App\Actions\Emails;

use App\Enums\EmailStatus;
use App\Models\Email;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ScheduleEmailSend
{
    public function __construct(private BuildSendReadiness $sendReadiness) {}

    /**
     * Set a draft to send at a later time, or move an existing schedule. The
     * same checks as sending now run up front so a problem shows today, not
     * when the time comes; they run again when it fires.
     */
    public function schedule(Email $email, CarbonInterface $sendAt): void
    {
        DB::transaction(function () use ($email, $sendAt): void {
            $lockedEmail = Email::query()->lockForUpdate()->findOrFail($email->id);

            if ($lockedEmail->status !== EmailStatus::Draft) {
                throw ValidationException::withMessages(['scheduled_at' => __('Only a draft campaign can be scheduled.')]);
            }

            $readiness = $this->sendReadiness->handle($lockedEmail);
            $problem = collect($readiness['checks'])->firstWhere('passed', false);

            if ($problem !== null) {
                throw ValidationException::withMessages(['scheduled_at' => $problem['message']]);
            }

            $lockedEmail->update([
                'scheduled_at' => $sendAt,
                'schedule_error' => null,
            ]);
        });
    }

    public function cancel(Email $email): void
    {
        Email::query()
            ->whereKey($email->id)
            ->where('status', EmailStatus::Draft)
            ->update(['scheduled_at' => null, 'schedule_error' => null]);
    }

    /**
     * Take the schedule of a draft whose time has come, so exactly one runner
     * starts it. A draft that was rescheduled or cancelled in the meantime no
     * longer matches and is left alone.
     */
    public function claimDue(Email $email): bool
    {
        return Email::query()
            ->whereKey($email->id)
            ->where('status', EmailStatus::Draft)
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '<=', now())
            ->update(['scheduled_at' => null]) === 1;
    }
}
