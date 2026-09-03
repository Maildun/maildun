<?php

namespace Database\Factories;

use App\Enums\AutomationStatus;
use App\Enums\AutomationTrigger;
use App\Models\Automation;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Automation>
 */
class AutomationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'name' => fake()->unique()->words(3, true),
            'description' => fake()->optional()->sentence(),
            'status' => AutomationStatus::Draft,
            'trigger' => AutomationTrigger::Subscribed,
            'trigger_config' => ['audience_uuid' => null, 'tag_uuid' => null],
            'graph' => Automation::defaultGraph(),
            'trigger_token' => Automation::generateTriggerToken(),
        ];
    }

    public function active(): static
    {
        return $this->state(fn (): array => [
            'status' => AutomationStatus::Active,
        ]);
    }

    public function paused(): static
    {
        return $this->state(fn (): array => [
            'status' => AutomationStatus::Paused,
        ]);
    }

    public function triggeredBy(AutomationTrigger $trigger): static
    {
        $graph = Automation::defaultGraph();
        $graph['nodes'][0]['data']['kind'] = $trigger->value;

        return $this->state(fn (): array => [
            'trigger' => $trigger,
            'graph' => $graph,
        ]);
    }
}
