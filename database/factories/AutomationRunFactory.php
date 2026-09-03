<?php

namespace Database\Factories;

use App\Enums\AutomationRunStatus;
use App\Models\Automation;
use App\Models\AutomationRun;
use App\Models\Subscriber;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AutomationRun>
 */
class AutomationRunFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'automation_id' => Automation::factory(),
            'subscriber_id' => Subscriber::factory(),
            'status' => AutomationRunStatus::Pending,
            'graph' => Automation::defaultGraph(),
            'context' => [],
        ];
    }
}
