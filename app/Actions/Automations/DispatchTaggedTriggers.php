<?php

namespace App\Actions\Automations;

use App\Enums\AutomationTrigger;
use App\Events\SubscriberLifecycleOccurred;
use App\Models\Automation;
use App\Models\Contact;

class DispatchTaggedTriggers
{
    /**
     * Fire the tagged automation trigger for tags newly added to a contact.
     *
     * Tags belong to the contact, so every audience membership gains them and
     * each one is offered to the tagged automations. A tag added by an
     * automation step names that automation as the source so it never starts
     * itself again through another membership of the same contact.
     *
     * @param  list<string>  $tagUuids
     */
    public function handle(Contact $contact, array $tagUuids, ?Automation $source = null): void
    {
        if ($tagUuids === []) {
            return;
        }

        $context = $source === null ? [] : ['source_automation_uuid' => $source->uuid];

        foreach ($contact->subscribers()->get() as $subscriber) {
            foreach ($tagUuids as $tagUuid) {
                event(new SubscriberLifecycleOccurred(
                    AutomationTrigger::Tagged,
                    $subscriber,
                    ['tag_uuid' => $tagUuid, ...$context],
                ));
            }
        }
    }
}
