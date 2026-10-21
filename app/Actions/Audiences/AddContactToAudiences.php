<?php

namespace App\Actions\Audiences;

use App\Enums\AutomationTrigger;
use App\Enums\SubscriberLanguage;
use App\Enums\SubscriberSource;
use App\Enums\SubscriberStatus;
use App\Events\SubscriberLifecycleOccurred;
use App\Models\Audience;
use App\Models\AudienceOptOut;
use App\Models\Contact;
use App\Models\Subscriber;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;

class AddContactToAudiences
{
    /**
     * A new membership takes the given language, or the audience default. It
     * is set before the save so Subscribed automations see it.
     *
     * @param  Collection<int, Audience>  $audiences
     * @param  string|null  $consentText  Where consent came from, when more is known than "a team member".
     * @param  CarbonInterface|null  $consentedAt  When consent was attested, if not now.
     */
    public function handle(
        Contact $contact,
        Collection $audiences,
        ?string $ipAddress,
        ?string $consentText = null,
        ?CarbonInterface $consentedAt = null,
        SubscriberLanguage|string|null $language = null,
    ): int {
        $changedMemberships = 0;

        foreach ($audiences as $audience) {
            $subscriber = $audience->subscribers()->firstOrNew(['email' => $contact->email]);

            // A deleted membership that had opted out comes back unsubscribed
            // (the model restores it on create). Re-adding is never consent to
            // resubscribe; that takes an explicit resubscribe.
            if (! $subscriber->exists && AudienceOptOut::existsFor($audience->id, $contact->email)) {
                $subscriber->fill([
                    'contact_id' => $contact->id,
                    'first_name' => $contact->first_name,
                    'last_name' => $contact->last_name,
                    'language' => $audience->default_language,
                ])->save();

                continue;
            }

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

            // A resubscribe keeps where the membership came from; only a new
            // membership is a manual add.
            if (! $subscriber->exists) {
                $subscriber->fill([
                    'source' => SubscriberSource::Manual,
                    'subscribe_form_id' => null,
                ]);
            }

            $subscriber->fill([
                'contact_id' => $contact->id,
                'email' => $contact->email,
                'first_name' => $contact->first_name,
                'last_name' => $contact->last_name,
                'language' => $subscriber->language ?? $language ?? $audience->default_language,
                'status' => SubscriberStatus::Subscribed,
                'consent_text' => $consentText ?? 'Marketing consent confirmed by a team member.',
                'consented_at' => $consentedAt ?? now(),
                'consent_ip' => $ipAddress,
                'subscribed_at' => now(),
                'unsubscribed_at' => null,
                'resubscribe_requested_at' => null,
            ]);
            $subscriber->save();

            $changedMemberships++;
            event(new SubscriberLifecycleOccurred($trigger, $subscriber));
        }

        return $changedMemberships;
    }

    /**
     * A membership still awaiting double opt-in confirmation is left alone:
     * only the subscriber's own confirmation may make it sendable, and its
     * form consent record must survive a teammate adding them again.
     */
    private function lifecycleTrigger(Subscriber $subscriber): ?AutomationTrigger
    {
        if ($subscriber->status === SubscriberStatus::Unsubscribed || $subscriber->unsubscribed_at !== null) {
            return AutomationTrigger::Resubscribed;
        }

        if ($subscriber->exists && $subscriber->isPendingConfirmation()) {
            return null;
        }

        if (! $subscriber->exists || $subscriber->subscribed_at === null) {
            return AutomationTrigger::Subscribed;
        }

        return null;
    }
}
