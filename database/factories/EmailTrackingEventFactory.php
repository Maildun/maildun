<?php

namespace Database\Factories;

use App\Enums\EmailTrackingClassification;
use App\Enums\EmailTrackingEventType;
use App\Models\EmailDelivery;
use App\Models\EmailTrackingEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmailTrackingEvent>
 */
class EmailTrackingEventFactory extends Factory
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
            'email_link_id' => null,
            'type' => EmailTrackingEventType::Open,
            'occurred_at' => now(),
            'processed_at' => null,
            'last_dispatched_at' => null,
            'processing_attempts' => 0,
            'last_error' => null,
            'user_agent' => fake()->userAgent(),
            'ip_hash' => hash('sha256', fake()->ipv4()),
            'is_bot' => null,
            'is_proxy' => null,
            'classification' => EmailTrackingClassification::Unknown,
            'classification_reason' => 'factory_default',
            'client_family' => null,
            'device_type' => 'unknown',
            'country_code' => null,
            'subdivision_code' => null,
            'subdivision_name' => null,
            'city_name' => null,
            'latitude' => null,
            'longitude' => null,
            'network_asn' => null,
            'network_name' => null,
            'geolocation_source' => null,
            'geolocated_at' => null,
        ];
    }
}
