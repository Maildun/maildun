<?php

namespace App\Actions\Emails;

use App\Enums\EmailDeliveryStatus;
use App\Enums\EmailStatus;
use App\Models\Email;

class ResolveCampaignOutcome
{
    /**
     * The one rule for a finished campaign's status. A send whose recipients
     * were not all loaded is Failed; otherwise it is Sent when nothing failed
     * or was rejected, Failed when every recipient did, and Partially failed
     * in between. Bounces and complaints are recipient outcomes, not send
     * failures, so they never change it.
     */
    public function handle(Email $email): EmailStatus
    {
        // A person stopped it; that stays the outcome whatever finished.
        if ($email->status === EmailStatus::Stopped) {
            return EmailStatus::Stopped;
        }

        if ($email->deliveries()->count() < $email->recipient_count) {
            return EmailStatus::Failed;
        }

        $failed = $email->deliveries()->whereIn('status', [
            EmailDeliveryStatus::Failed,
            EmailDeliveryStatus::Rejected,
        ])->count();

        return match (true) {
            $failed === 0 => EmailStatus::Sent,
            $failed >= $email->recipient_count => EmailStatus::Failed,
            default => EmailStatus::PartiallyFailed,
        };
    }

    /**
     * Re-resolve a campaign that has already finished, after late provider
     * feedback changed one of its deliveries. In-flight and draft campaigns
     * are left alone; FinalizeEmailSend resolves those when they finish.
     */
    public function refresh(Email $email): void
    {
        if ($email->status === EmailStatus::Draft || $email->status->isActive()) {
            return;
        }

        $status = $this->handle($email);

        if ($status !== $email->status) {
            $email->update(['status' => $status]);
        }
    }
}
