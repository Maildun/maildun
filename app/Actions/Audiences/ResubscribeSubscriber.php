<?php

namespace App\Actions\Audiences;

use App\Enums\AutomationTrigger;
use App\Enums\SubscriberStatus;
use App\Events\SubscriberLifecycleOccurred;
use App\Models\Subscriber;

class ResubscribeSubscriber
{
    /**
     * Restore an audience membership on a team member's confirmation that the
     * person consented again, and fire the resubscribed automation trigger.
     */
    public function handle(Subscriber $subscriber, ?string $ipAddress): Subscriber
    {
        $subscriber->update([
            'status' => SubscriberStatus::Subscribed,
            'consent_text' => 'Marketing consent confirmed by a team member.',
            'consented_at' => now(),
            'consent_ip' => $ipAddress,
            'subscribed_at' => now(),
            'unsubscribed_at' => null,
        ]);

        $subscriber = $subscriber->fresh() ?? $subscriber;
        event(new SubscriberLifecycleOccurred(AutomationTrigger::Resubscribed, $subscriber));

        return $subscriber;
    }
}
