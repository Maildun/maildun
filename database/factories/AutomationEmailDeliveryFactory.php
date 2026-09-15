<?php

namespace Database\Factories;

use App\Enums\EmailDeliveryStatus;
use App\Enums\EmailProvider;
use App\Models\AutomationEmailDelivery;
use App\Models\AutomationRun;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AutomationEmailDelivery>
 */
class AutomationEmailDeliveryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'team_id' => fn (array $attributes): int => AutomationRun::query()
                ->with('automation:id,team_id')
                ->whereKey($attributes['automation_run_id'])
                ->sole()
                ->automation
                ->team_id,
            'automation_run_id' => AutomationRun::factory(),
            'transactional_email_id' => null,
            'subscriber_id' => null,
            'to_address' => fake()->unique()->safeEmail(),
            'status' => EmailDeliveryStatus::Sent,
            'provider' => EmailProvider::AmazonSes,
            'sent_at' => now(),
        ];
    }
}
