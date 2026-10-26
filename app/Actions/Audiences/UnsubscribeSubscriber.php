<?php

namespace App\Actions\Audiences;

use App\Enums\AutomationTrigger;
use App\Enums\SubscriberStatus;
use App\Events\SubscriberLifecycleOccurred;
use App\Models\Subscriber;
use Illuminate\Support\Facades\DB;

class UnsubscribeSubscriber
{
    /**
     * The shared opt-out transition. Idempotent: opting out someone already
     * unsubscribed neither restamps unsubscribed_at nor fires the lifecycle
     * event again, but it still cancels a pending request to rejoin.
     */
    public function handle(Subscriber $subscriber): Subscriber
    {
        $unsubscribed = false;

        $subscriber = DB::transaction(function () use ($subscriber, &$unsubscribed): Subscriber {
            $lockedSubscriber = Subscriber::query()->whereKey($subscriber->id)->lockForUpdate()->firstOrFail();

            if ($lockedSubscriber->status === SubscriberStatus::Unsubscribed) {
                $lockedSubscriber->update(['resubscribe_requested_at' => null]);

                return $lockedSubscriber;
            }

            $lockedSubscriber->update([
                'status' => SubscriberStatus::Unsubscribed,
                'unsubscribed_at' => now(),
                'resubscribe_requested_at' => null,
            ]);
            $unsubscribed = true;

            return $lockedSubscriber;
        });

        if ($unsubscribed) {
            event(new SubscriberLifecycleOccurred(AutomationTrigger::Unsubscribed, $subscriber));
        }

        return $subscriber;
    }
}
