<?php

namespace App\Listeners;

use App\Actions\Automations\DispatchAutomationTrigger;
use App\Events\SubscriberLifecycleOccurred;

class DispatchAutomationRuns
{
    public function __construct(private DispatchAutomationTrigger $dispatch) {}

    public function handle(SubscriberLifecycleOccurred $event): void
    {
        $this->dispatch->handle($event->trigger, $event->subscriber, $event->context);
    }
}
