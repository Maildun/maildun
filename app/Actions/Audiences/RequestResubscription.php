<?php

namespace App\Actions\Audiences;

use App\Models\Audience;
use App\Models\Subscriber;

class RequestResubscription
{
    public function __construct(private QueueAudienceConfirmationEmail $queueConfirmationEmail) {}

    /**
     * Record that someone asked to rejoin a double opt-in audience they left,
     * and email them a confirmation link. The opt-out stands until they click
     * it, and nothing from the unverified submission besides the consent
     * record touches their profile.
     *
     * While a confirmation is cooling down, the pending request keeps its
     * timestamp so the link already in their inbox still works.
     */
    public function handle(Audience $audience, Subscriber $subscriber, ?string $consentText, ?string $ipAddress): Subscriber
    {
        $keepsSentLink = $subscriber->isPendingResubscription()
            && $this->queueConfirmationEmail->isCoolingDown($subscriber);

        $subscriber->update([
            'consent_text' => $consentText,
            'consented_at' => now(),
            'consent_ip' => $ipAddress,
            'subscribed_at' => null,
            'resubscribe_requested_at' => $keepsSentLink ? $subscriber->resubscribe_requested_at : now(),
        ]);

        // Reload so the signed link carries the timestamp as the database stores it.
        $subscriber = $subscriber->fresh() ?? $subscriber;
        $this->queueConfirmationEmail->handle($audience, $subscriber);

        return $subscriber;
    }
}
