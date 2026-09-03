<?php

namespace Database\Factories;

use App\Models\Email;
use App\Models\EmailLink;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmailLink>
 */
class EmailLinkFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'email_id' => Email::factory(),
            'url' => $url = fake()->unique()->url(),
            'url_hash' => hash('sha256', $url),
            'position' => 0,
        ];
    }
}
