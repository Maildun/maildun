<?php

namespace App\Actions\Audiences;

use App\Enums\AutomationTrigger;
use App\Enums\SubscriberStatus;
use App\Events\SubscriberLifecycleOccurred;
use App\Models\Subscriber;
use Illuminate\Support\Facades\DB;

class ResubscribeSubscriber
{
    /**
     * Restore an audience membership on a team member's confirmation that the
     * person consented again, and fire the resubscribed automation trigger.
     * Only an unsubscribed member changes: anyone else keeps their consent
     * record and no automation runs again.
     */
    public function handle(Subscriber $subscriber, ?string $ipAddress): Subscriber
    {
        $resubscribed = false;

        $subscriber = DB::transaction(function () use ($subscriber, $ipAddress, &$resubscribed): Subscriber {
            $lockedSubscriber = Subscriber::query()->whereKey($subscriber->id)->lockForUpdate()->firstOrFail();

            if ($lockedSubscriber->status !== SubscriberStatus::Unsubscribed) {
                return $lockedSubscriber;
            }

            $lockedSubscriber->update([
                'status' => SubscriberStatus::Subscribed,
                'consent_text' => 'Marketing consent confirmed by a team member.',
                'consented_at' => now(),
                'consent_ip' => $ipAddress,
                'subscribed_at' => now(),
                'unsubscribed_at' => null,
                'resubscribe_requested_at' => null,
            ]);
            $resubscribed = true;

            return $lockedSubscriber->fresh() ?? $lockedSubscriber;
        });

        if ($resubscribed) {
            event(new SubscriberLifecycleOccurred(AutomationTrigger::Resubscribed, $subscriber));
        }

        return $subscriber;
    }
}
