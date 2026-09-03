<?php

namespace Database\Factories;

use App\Models\AutomationRun;
use App\Models\AutomationRunStep;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AutomationRunStep>
 */
class AutomationRunStepFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'automation_run_id' => AutomationRun::factory(),
            'node_id' => 'trigger',
            'status' => 'completed',
            'result' => [],
            'processed_at' => now(),
        ];
    }
}
