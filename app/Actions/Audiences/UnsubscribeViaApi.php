<?php

namespace App\Actions\Audiences;

use App\Enums\AutomationTrigger;
use App\Enums\SubscriberStatus;
use App\Events\SubscriberLifecycleOccurred;
use App\Models\Audience;
use App\Models\Subscriber;
use Illuminate\Support\Facades\DB;

class UnsubscribeViaApi
{
    public function handle(Audience $audience, string $email): ?Subscriber
    {
        $changed = false;

        $subscriber = DB::transaction(function () use ($audience, $email, &$changed): ?Subscriber {
            $subscriber = $audience->subscribers()
                ->where('email', $email)
                ->lockForUpdate()
                ->first();

            if (! $subscriber instanceof Subscriber || $subscriber->status === SubscriberStatus::Unsubscribed) {
                return $subscriber;
            }

            $subscriber->update([
                'status' => SubscriberStatus::Unsubscribed,
                'unsubscribed_at' => now(),
            ]);
            $changed = true;

            return $subscriber->fresh() ?? $subscriber;
        });

        if ($changed && $subscriber instanceof Subscriber) {
            event(new SubscriberLifecycleOccurred(AutomationTrigger::Unsubscribed, $subscriber));
        }

        return $subscriber;
    }
}
