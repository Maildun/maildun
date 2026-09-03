<?php

namespace Database\Factories;

use App\Enums\EmailDeliveryStatus;
use App\Enums\EmailProvider;
use App\Models\EmailDelivery;
use App\Models\EmailDeliveryAttempt;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmailDeliveryAttempt>
 */
class EmailDeliveryAttemptFactory extends Factory
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
            'provider' => EmailProvider::Smtp,
            'status' => EmailDeliveryStatus::Sending,
            'send_attempted_at' => now(),
        ];
    }

    public function ses(): static
    {
        return $this->state(fn (): array => [
            'provider' => EmailProvider::AmazonSes,
            'ses_configuration_set' => 'maildun-test',
            'ses_sns_topic_arn_hash' => hash('sha256', 'arn:aws:sns:us-east-1:123456789012:maildun-test'),
        ]);
    }
}
