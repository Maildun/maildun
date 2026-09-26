<?php

namespace App\Actions\Emails;

use App\Enums\EmailDeliveryStatus;
use App\Enums\EmailFailureCode;
use App\Models\Email;
use App\Models\EmailDeliveryAttempt;
use Illuminate\Bus\Batch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class FinalizeEmailSend
{
    public function __construct(public int $emailId) {}

    /**
     * The batch argument is what Bus passes to a finally callback. It is
     * nullable so emails:resume can finalize a campaign whose batch record is
     * already gone, which is exactly the case that strands a campaign.
     */
    public function __invoke(?Batch $batch = null): void
    {
        $email = Email::query()->find($this->emailId);

        if ($email === null) {
            return;
        }

        $failureReason = __('The delivery did not report back before the campaign finished.');

        DB::transaction(function () use ($email, $failureReason): void {
            // A worker killed after the transport accepted a message leaves the
            // parent and attempt claimed. A failed loader may also leave queued
            // rows that never received a delivery job. Close both states while
            // retaining them for late SES feedback or a deliberate retry.
            EmailDeliveryAttempt::query()
                ->where('status', EmailDeliveryStatus::Sending)
                ->whereHas('delivery', function (Builder $deliveries) use ($email): void {
                    $deliveries
                        ->where('email_id', $email->id)
                        ->where('status', EmailDeliveryStatus::Sending);
                })
                ->update([
                    'status' => EmailDeliveryStatus::Failed,
                    'failure_reason' => $failureReason,
                ]);

            $email->deliveries()
                ->whereIn('status', [EmailDeliveryStatus::Queued, EmailDeliveryStatus::Sending])
                ->update([
                    'status' => EmailDeliveryStatus::Failed,
                    'failure_reason' => $failureReason,
                    'failure_code' => EmailFailureCode::NoReport,
                ]);
        }, attempts: 3);

        $email->sendRuns()->whereNull('finished_at')->update(['finished_at' => now()]);

        $email->update([
            'status' => app(ResolveCampaignOutcome::class)->handle($email),
            'sent_at' => now(),
        ]);
    }
}
