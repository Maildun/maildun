<?php

namespace Database\Factories;

use App\Models\EmailDelivery;
use App\Models\EmailLink;
use App\Models\EmailLinkClick;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmailLinkClick>
 */
class EmailLinkClickFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'email_delivery_id' => EmailDelivery::factory(),
            'email_link_id' => EmailLink::factory(),
            'clicks_count' => 1,
            'first_clicked_at' => now(),
            'last_clicked_at' => now(),
        ];
    }
}
