<?php

namespace App\Actions\Audiences;

use App\Enums\AutomationTrigger;
use App\Enums\SubscriberStatus;
use App\Events\SubscriberLifecycleOccurred;
use App\Models\Subscriber;
use Illuminate\Support\Facades\DB;

class ConfirmSubscriberSubscription
{
    public function handle(Subscriber $subscriber): Subscriber
    {
        $confirmed = false;

        $subscriber = DB::transaction(function () use ($subscriber, &$confirmed): Subscriber {
            $lockedSubscriber = Subscriber::query()
                ->with('audience')
                ->whereKey($subscriber->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedSubscriber->status !== SubscriberStatus::Subscribed
                || $lockedSubscriber->subscribed_at !== null) {
                return $lockedSubscriber;
            }

            $lockedSubscriber->update(['subscribed_at' => now()]);
            $confirmed = true;

            return $lockedSubscriber->fresh(['audience']) ?? $lockedSubscriber;
        });

        if ($confirmed) {
            event(new SubscriberLifecycleOccurred(AutomationTrigger::Subscribed, $subscriber));
        }

        return $subscriber;
    }
}
