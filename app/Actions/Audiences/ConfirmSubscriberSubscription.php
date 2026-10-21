<?php

namespace App\Actions\Audiences;

use App\Enums\AutomationTrigger;
use App\Enums\SubscriberStatus;
use App\Events\SubscriberLifecycleOccurred;
use App\Models\Subscriber;
use Illuminate\Support\Facades\DB;

class ConfirmSubscriberSubscription
{
    public function __construct(private NotifyAudienceOfSubscriptionChange $notifyAudience) {}

    /**
     * Confirm a double opt-in from the subscriber's own link. A first signup
     * fires Subscribed. A rejoin after an opt-out fires Resubscribed, and only
     * when the link carries the current resubscribe_requested_at, so a link
     * from an older request or from before a later opt-out does nothing.
     */
    public function handle(Subscriber $subscriber, ?string $resubscribeToken = null): Subscriber
    {
        $trigger = null;

        $subscriber = DB::transaction(function () use ($subscriber, $resubscribeToken, &$trigger): Subscriber {
            $lockedSubscriber = Subscriber::query()
                ->with('audience')
                ->whereKey($subscriber->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedSubscriber->isPendingResubscription()) {
                if ($resubscribeToken !== (string) $lockedSubscriber->resubscribe_requested_at?->getTimestamp()) {
                    return $lockedSubscriber;
                }

                $lockedSubscriber->update([
                    'status' => SubscriberStatus::Subscribed,
                    'subscribed_at' => now(),
                    'unsubscribed_at' => null,
                    'resubscribe_requested_at' => null,
                ]);
                $trigger = AutomationTrigger::Resubscribed;

                return $lockedSubscriber->fresh(['audience']) ?? $lockedSubscriber;
            }

            if (! $lockedSubscriber->isPendingConfirmation()) {
                return $lockedSubscriber;
            }

            $lockedSubscriber->update(['subscribed_at' => now()]);
            $trigger = AutomationTrigger::Subscribed;

            return $lockedSubscriber->fresh(['audience']) ?? $lockedSubscriber;
        });

        if ($trigger instanceof AutomationTrigger) {
            event(new SubscriberLifecycleOccurred($trigger, $subscriber));
            $this->notifyAudience->handle($subscriber, subscribed: true);
        }

        return $subscriber;
    }
}
