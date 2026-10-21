<?php

namespace Database\Factories;

use App\Models\Audience;
use App\Models\AudienceSubscriberSnapshot;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AudienceSubscriberSnapshot> */
class AudienceSubscriberSnapshotFactory extends Factory
{
    public function definition(): array
    {
        return [
            'audience_id' => Audience::factory(),
            'day' => now()->subDay()->toDateString(),
            'total' => 0,
            'unsubscribed' => 0,
            'pending' => 0,
            'new' => 0,
        ];
    }
}
