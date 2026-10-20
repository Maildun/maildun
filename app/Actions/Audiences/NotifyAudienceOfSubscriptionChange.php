<?php

namespace App\Actions\Audiences;

use App\Models\Subscriber;
use App\Notifications\AudienceSubscriptionChanged;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

/**
 * Emails the audience's notification address when someone subscribes or
 * unsubscribes on their own: through a form, the API, a confirmation link, or
 * an unsubscribe link. Imports and changes the team makes in the app send
 * nothing, so a bulk action never floods the inbox.
 */
class NotifyAudienceOfSubscriptionChange
{
    public function handle(Subscriber $subscriber, bool $subscribed): void
    {
        $audience = $subscriber->audience()->with('team')->first();

        if ($audience === null || blank($audience->notification_email)) {
            return;
        }

        Notification::route('mail', $audience->notification_email)
            ->notify(new AudienceSubscriptionChanged(
                audienceName: $audience->name,
                email: $subscriber->email,
                name: Str::of($subscriber->first_name.' '.$subscriber->last_name)->squish()->toString(),
                subscribed: $subscribed,
                profileUrl: route('audiences.subscribers.show', [$audience->team, $audience, $subscriber]),
            ));
    }
}
