<?php

namespace Database\Factories;

use App\Enums\SubscriberSource;
use App\Enums\SubscriberStatus;
use App\Models\Audience;
use App\Models\SubscribeForm;
use App\Models\Subscriber;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Subscriber> */
class SubscriberFactory extends Factory
{
    public function definition(): array
    {
        return [
            'audience_id' => Audience::factory(),
            'email' => fake()->unique()->safeEmail(),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'status' => SubscriberStatus::Subscribed,
            'source' => SubscriberSource::Manual,
            'consent_text' => 'Marketing consent confirmed.',
            'consented_at' => now(),
            'subscribed_at' => now(),
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Subscriber $subscriber): void {
            $audience = $subscriber->audience;

            $contact = $audience->team->contacts()->firstOrCreate(
                ['email' => $subscriber->email],
                [
                    'first_name' => $subscriber->first_name,
                    'last_name' => $subscriber->last_name,
                ],
            );

            $contact->update([
                'first_name' => $subscriber->first_name,
                'last_name' => $subscriber->last_name,
            ]);

            $subscriber->updateQuietly([
                'contact_id' => $contact->id,
                'email' => $contact->email,
                'first_name' => $contact->first_name,
                'last_name' => $contact->last_name,
            ]);
        });
    }

    public function unsubscribed(?CarbonInterface $at = null): static
    {
        return $this->state(fn (): array => [
            'status' => SubscriberStatus::Unsubscribed,
            'unsubscribed_at' => $at ?? now(),
        ]);
    }

    /**
     * A double opt-in signup who has not clicked the confirmation link yet.
     */
    public function pendingConfirmation(): static
    {
        return $this->state(fn (): array => [
            'status' => SubscriberStatus::Subscribed,
            'subscribed_at' => null,
        ]);
    }

    public function fromForm(SubscribeForm $form): static
    {
        return $this->state(fn (): array => [
            'source' => SubscriberSource::Form,
            'subscribe_form_id' => $form->id,
        ]);
    }

    public function joinedAt(CarbonInterface $joinedAt): static
    {
        return $this->state(fn (): array => [
            'consented_at' => $joinedAt,
            'subscribed_at' => $joinedAt,
            'created_at' => $joinedAt,
            'updated_at' => $joinedAt,
        ]);
    }
}
