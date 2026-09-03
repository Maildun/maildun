<?php

namespace App\Actions\Automations;

use App\Enums\AutomationStatus;
use App\Enums\AutomationTrigger;
use App\Models\Automation;
use App\Models\Subscriber;

class DispatchAutomationTrigger
{
    public function __construct(private StartAutomationRun $start) {}

    /**
     * @param  array<string, mixed>  $context
     */
    public function handle(AutomationTrigger $trigger, Subscriber $subscriber, array $context = []): void
    {
        $subscriber->loadMissing('audience.team');

        $team = $subscriber->audience->team;
        $tagUuid = is_string($context['tag_uuid'] ?? null) ? $context['tag_uuid'] : null;

        Automation::query()
            ->where('team_id', $team->id)
            ->where('status', AutomationStatus::Active)
            ->where('trigger', $trigger)
            ->each(function (Automation $automation) use ($subscriber, $tagUuid, $context): void {
                if (! $this->matches($automation, $subscriber, $tagUuid)) {
                    return;
                }

                $this->start->handle($automation, $subscriber, $context);
            });
    }

    protected function matches(Automation $automation, Subscriber $subscriber, ?string $tagUuid): bool
    {
        $audienceUuid = $automation->trigger_config['audience_uuid'] ?? null;

        if (filled($audienceUuid) && $audienceUuid !== $subscriber->audience->uuid) {
            return false;
        }

        if ($automation->trigger !== AutomationTrigger::Tagged) {
            return true;
        }

        $requiredTag = $automation->trigger_config['tag_uuid'] ?? null;

        return blank($requiredTag) || $requiredTag === $tagUuid;
    }
}
