<?php

namespace App\Actions\Audiences;

use App\Enums\AutomationTrigger;
use App\Enums\SubscriberSource;
use App\Enums\SubscriberStatus;
use App\Events\SubscriberLifecycleOccurred;
use App\Models\Audience;
use App\Models\Contact;
use App\Models\Subscriber;
use Illuminate\Database\Eloquent\Collection;

class AddContactToAudiences
{
    /** @param Collection<int, Audience> $audiences */
    public function handle(Contact $contact, Collection $audiences, ?string $ipAddress): int
    {
        $changedMemberships = 0;

        foreach ($audiences as $audience) {
            $subscriber = $audience->subscribers()->firstOrNew(['email' => $contact->email]);
            $trigger = $this->lifecycleTrigger($subscriber);

            if ($trigger === null) {
                if ($subscriber->contact_id !== $contact->id) {
                    $subscriber->update([
                        'contact_id' => $contact->id,
                        'email' => $contact->email,
                        'first_name' => $contact->first_name,
                        'last_name' => $contact->last_name,
                    ]);
                }

                continue;
            }

            $subscriber->fill([
                'contact_id' => $contact->id,
                'email' => $contact->email,
                'first_name' => $contact->first_name,
                'last_name' => $contact->last_name,
                'status' => SubscriberStatus::Subscribed,
                'source' => SubscriberSource::Manual,
                'consent_text' => 'Marketing consent confirmed by a team member.',
                'consented_at' => now(),
                'consent_ip' => $ipAddress,
                'subscribed_at' => now(),
                'unsubscribed_at' => null,
            ]);
            $subscriber->save();

            $changedMemberships++;
            event(new SubscriberLifecycleOccurred($trigger, $subscriber));
        }

        return $changedMemberships;
    }

    private function lifecycleTrigger(Subscriber $subscriber): ?AutomationTrigger
    {
        if ($subscriber->status === SubscriberStatus::Unsubscribed || $subscriber->unsubscribed_at !== null) {
            return AutomationTrigger::Resubscribed;
        }

        if (! $subscriber->exists || $subscriber->subscribed_at === null) {
            return AutomationTrigger::Subscribed;
        }

        return null;
    }
}
