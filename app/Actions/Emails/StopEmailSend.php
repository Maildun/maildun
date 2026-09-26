<?php

namespace App\Actions\Emails;

use App\Enums\EmailDeliveryStatus;
use App\Enums\EmailStatus;
use App\Models\Email;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StopEmailSend
{
    /**
     * Stop a queued or sending campaign. Deliveries not yet claimed are
     * cancelled and never sent; a delivery already handed to the provider
     * cannot be recalled and finishes normally. The claim in SendEmailDelivery
     * requires an active campaign, so a racing worker either claimed first
     * (and sends) or sees the stop (and sends nothing).
     *
     * @return int How many recipients were cancelled.
     */
    public function handle(Email $email): int
    {
        $cancelled = DB::transaction(function () use ($email): int {
            $lockedEmail = Email::query()->lockForUpdate()->findOrFail($email->id);

            if (! $lockedEmail->status->isActive()) {
                throw ValidationException::withMessages(['email' => __('This campaign is not sending.')]);
            }

            $lockedEmail->update(['status' => EmailStatus::Stopped]);

            return $lockedEmail->deliveries()
                ->where('status', EmailDeliveryStatus::Queued)
                ->whereNull('send_attempted_at')
                ->update([
                    'status' => EmailDeliveryStatus::Cancelled,
                    'failure_reason' => __('The campaign was stopped before this recipient was sent to.'),
                ]);
        }, attempts: 3);

        // Remaining jobs would lose their claim anyway; cancelling the batch
        // just stops the loader from adding more of them.
        if ($email->batch_id !== null) {
            Bus::findBatch($email->batch_id)?->cancel();
        }

        return $cancelled;
    }
}
