<?php

namespace App\Actions\Audiences;

use App\Enums\AutomationTrigger;
use App\Enums\SubscriberSource;
use App\Enums\SubscriberStatus;
use App\Events\SubscriberLifecycleOccurred;
use App\Models\Audience;
use App\Models\Subscriber;
use App\Services\ManageContact;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class SubscribeViaApi
{
    public function __construct(
        private QueueAudienceConfirmationEmail $queueConfirmationEmail,
        private ManageContact $manageContact,
    ) {}

    /** @param array{email: string, first_name?: string|null, last_name?: string|null, consent_text?: string|null, attributes?: array<string, string|int|float|null>} $data */
    public function handle(Audience $audience, array $data, ?string $ipAddress): Subscriber
    {
        $trigger = null;

        $subscriber = DB::transaction(function () use ($audience, $data, $ipAddress, &$trigger): Subscriber {
            $lockedAudience = Audience::query()->whereKey($audience->id)->lockForUpdate()->firstOrFail();
            $subscriber = $lockedAudience->subscribers()->where('email', $data['email'])->first();
            $attributeValues = Arr::where(
                $data['attributes'] ?? [],
                fn (mixed $value): bool => $value !== null && $value !== '',
            );
            $contact = $this->manageContact->findOrCreate($lockedAudience->team, [
                'email' => $data['email'],
                'first_name' => $data['first_name'] ?? null,
                'last_name' => $data['last_name'] ?? null,
            ]);
            $profile = [
                'contact_id' => $contact->id,
                'email' => $contact->email,
                'first_name' => $contact->first_name,
                'last_name' => $contact->last_name,
            ];

            if ($subscriber?->status === SubscriberStatus::Subscribed && ! $subscriber->isPendingConfirmation()) {
                $subscriber->update([
                    ...$profile,
                    ...($attributeValues === [] ? [] : [
                        'attribute_values' => [
                            ...($subscriber->attribute_values ?? []),
                            ...$attributeValues,
                        ],
                    ]),
                ]);

                return $subscriber->fresh() ?? $subscriber;
            }

            $requiresConfirmation = $lockedAudience->double_opt_in;

            if (! $requiresConfirmation) {
                $trigger = $subscriber?->status === SubscriberStatus::Unsubscribed
                    ? AutomationTrigger::Resubscribed
                    : AutomationTrigger::Subscribed;
            }

            $values = [
                ...$profile,
                'subscribe_form_id' => null,
                'status' => SubscriberStatus::Subscribed,
                'source' => SubscriberSource::Api,
                'consent_text' => $data['consent_text'] ?? 'Marketing consent confirmed via API.',
                'consented_at' => now(),
                'consent_ip' => $ipAddress,
                'subscribed_at' => $requiresConfirmation ? null : now(),
                'unsubscribed_at' => null,
            ];

            if ($subscriber instanceof Subscriber) {
                if ($attributeValues !== []) {
                    $values['attribute_values'] = [
                        ...($subscriber->attribute_values ?? []),
                        ...$attributeValues,
                    ];
                }

                $subscriber->update($values);

                if ($requiresConfirmation) {
                    $this->queueConfirmationEmail->handle($lockedAudience, $subscriber->fresh() ?? $subscriber);
                }

                return $subscriber->fresh() ?? $subscriber;
            }

            $subscriber = $lockedAudience->subscribers()->create([
                ...$values,
                'attribute_values' => $attributeValues,
            ]);

            if ($requiresConfirmation) {
                $this->queueConfirmationEmail->handle($lockedAudience, $subscriber);
            }

            return $subscriber;
        });

        if ($trigger instanceof AutomationTrigger) {
            event(new SubscriberLifecycleOccurred($trigger, $subscriber));
        }

        return $subscriber;
    }
}
