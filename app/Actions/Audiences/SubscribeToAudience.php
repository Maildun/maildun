<?php

namespace App\Actions\Audiences;

use App\Enums\AutomationTrigger;
use App\Enums\SubscriberSource;
use App\Enums\SubscriberStatus;
use App\Events\SubscriberLifecycleOccurred;
use App\Models\Audience;
use App\Models\SubscribeForm;
use App\Models\Subscriber;
use App\Services\ManageContact;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class SubscribeToAudience
{
    public function __construct(
        private QueueAudienceConfirmationEmail $queueConfirmationEmail,
        private ManageContact $manageContact,
    ) {}

    /** @param array{email: string, first_name?: string|null, last_name?: string|null, attributes?: array<string, string|int|float|null>} $data */
    public function handle(SubscribeForm $subscribeForm, array $data, ?string $ipAddress): Subscriber
    {
        $trigger = null;

        $subscriber = DB::transaction(function () use ($subscribeForm, $data, $ipAddress, &$trigger): Subscriber {
            $audience = Audience::query()->whereKey($subscribeForm->audience_id)->lockForUpdate()->firstOrFail();
            $subscriber = $audience->subscribers()->where('email', $data['email'])->first();

            if ($subscriber?->status === SubscriberStatus::Subscribed && ! $subscriber->isPendingConfirmation()) {
                return $subscriber;
            }

            $contact = $this->manageContact->findOrCreate(
                $audience->team,
                [
                    'email' => $data['email'],
                    'first_name' => $data['first_name'] ?? null,
                    'last_name' => $data['last_name'] ?? null,
                ],
            );

            $requiresConfirmation = $audience->double_opt_in;

            if (! $requiresConfirmation) {
                $trigger = $subscriber?->status === SubscriberStatus::Unsubscribed
                    ? AutomationTrigger::Resubscribed
                    : AutomationTrigger::Subscribed;
            }

            $attributeValues = Arr::where(
                $data['attributes'] ?? [],
                fn (mixed $value): bool => $value !== null && $value !== '',
            );

            $values = [
                'contact_id' => $contact->id,
                'email' => $contact->email,
                'first_name' => $contact->first_name,
                'last_name' => $contact->last_name,
                'subscribe_form_id' => $subscribeForm->id,
                'status' => SubscriberStatus::Subscribed,
                'source' => SubscriberSource::Form,
                'consent_text' => $subscribeForm->consent_text,
                'consented_at' => now(),
                'consent_ip' => $ipAddress,
                'subscribed_at' => $requiresConfirmation ? null : now(),
                'unsubscribed_at' => null,
            ];

            if ($subscriber) {
                if ($attributeValues !== []) {
                    $values['attribute_values'] = [
                        ...($subscriber->attribute_values ?? []),
                        ...$attributeValues,
                    ];
                }

                $subscriber->update($values);

                if ($requiresConfirmation) {
                    $this->queueConfirmationEmail->handle($audience, $subscriber->fresh() ?? $subscriber);
                }

                return $subscriber->fresh() ?? $subscriber;
            }

            $subscriber = $audience->subscribers()->create([
                ...$values,
                'attribute_values' => $attributeValues,
            ]);

            if ($requiresConfirmation) {
                $this->queueConfirmationEmail->handle($audience, $subscriber);
            }

            return $subscriber;
        });

        if ($trigger instanceof AutomationTrigger) {
            event(new SubscriberLifecycleOccurred($trigger, $subscriber));
        }

        return $subscriber;
    }
}
