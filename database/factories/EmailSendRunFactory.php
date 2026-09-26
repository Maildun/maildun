<?php

namespace Database\Factories;

use App\Enums\EmailSendRunKind;
use App\Models\Email;
use App\Models\EmailSendRun;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmailSendRun>
 */
class EmailSendRunFactory extends Factory
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
            'kind' => EmailSendRunKind::Initial,
            'recipient_count' => 1,
            'started_at' => now(),
        ];
    }

    public function retry(): static
    {
        return $this->state(fn (): array => [
            'kind' => EmailSendRunKind::Retry,
        ]);
    }

    public function finished(): static
    {
        return $this->state(fn (): array => [
            'finished_at' => now(),
        ]);
    }
}
