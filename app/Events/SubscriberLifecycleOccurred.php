<?php

namespace App\Events;

use App\Enums\AutomationTrigger;
use App\Models\Subscriber;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SubscriberLifecycleOccurred
{
    use Dispatchable, SerializesModels;

    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        public AutomationTrigger $trigger,
        public Subscriber $subscriber,
        public array $context = [],
    ) {}
}
