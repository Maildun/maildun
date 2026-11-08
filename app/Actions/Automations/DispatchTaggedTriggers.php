<?php

namespace App\Actions\Automations;

use App\Enums\AutomationTrigger;
use App\Events\SubscriberLifecycleOccurred;
use App\Models\AutomationRun;
use App\Models\Contact;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class DispatchTaggedTriggers
{
    /**
     * How many automations in a row may start one another by adding tags.
     * Automation A tagging X for B, which tags Y for A, would otherwise loop
     * for as long as the contact exists.
     */
    public const int MAX_TRIGGER_DEPTH = 5;

    /**
     * Fire the tagged automation trigger for tags newly added to a contact.
     *
     * Tags belong to the contact, so every audience membership gains them and
     * each one is offered to the tagged automations. Every run started by this
     * one tagging shares its trigger_event_uuid, which lets a send step email
     * the contact once rather than once per membership.
     *
     * A tag added by an automation step names that run as the source: its
     * automation never starts itself again through another membership, and
     * the new runs sit one level deeper in the chain. Past MAX_TRIGGER_DEPTH
     * nothing starts and this returns false.
     *
     * @param  list<string>  $tagUuids
     */
    public function handle(Contact $contact, array $tagUuids, ?AutomationRun $source = null): bool
    {
        if ($tagUuids === []) {
            return true;
        }

        $depth = $source === null ? 0 : self::depthOf($source) + 1;

        if ($depth > self::MAX_TRIGGER_DEPTH) {
            Log::warning('Tagged automations not started: too many automations in a row started one another.', [
                'contact_id' => $contact->id,
                'tag_uuids' => $tagUuids,
                'source_run' => $source->uuid,
                'depth' => $depth,
            ]);

            return false;
        }

        $context = [
            'trigger_event_uuid' => (string) Str::uuid(),
            'trigger_depth' => $depth,
        ];

        if ($source !== null) {
            $context['source_automation_uuid'] = $source->automation->uuid;
        }

        foreach ($contact->subscribers()->get() as $subscriber) {
            foreach ($tagUuids as $tagUuid) {
                event(new SubscriberLifecycleOccurred(
                    AutomationTrigger::Tagged,
                    $subscriber,
                    ['tag_uuid' => $tagUuid, ...$context],
                ));
            }
        }

        return true;
    }

    /**
     * How many automations started one another to start this run; 0 when a
     * person or an import started it.
     */
    public static function depthOf(AutomationRun $run): int
    {
        return max(0, (int) ($run->context['trigger_depth'] ?? 0));
    }
}
