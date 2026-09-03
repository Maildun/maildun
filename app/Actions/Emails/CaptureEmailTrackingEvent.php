<?php

namespace App\Actions\Emails;

use App\Enums\EmailTrackingEventType;
use App\Models\EmailDelivery;
use App\Models\EmailLink;
use App\Models\EmailTrackingEvent;
use Carbon\CarbonInterface;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Throwable;

class CaptureEmailTrackingEvent
{
    public function __construct(
        private DispatchEmailTrackingEvent $dispatchEvent,
        private LookupEmailTrackingLocation $lookupLocation,
        private ClassifyEmailTrackingEvent $classifyEvent,
    ) {}

    public function handle(
        EmailDelivery $delivery,
        EmailTrackingEventType $type,
        ?EmailLink $link = null,
        ?CarbonInterface $occurredAt = null,
        ?string $userAgent = null,
        ?string $ipAddress = null,
    ): ?EmailTrackingEvent {
        $this->validateLink($delivery, $type, $link);
        $location = $this->lookupLocation->handle($ipAddress);
        $classification = $this->classifyEvent->handle($userAgent);

        try {
            $event = EmailTrackingEvent::query()->create([
                'email_delivery_id' => $delivery->id,
                'email_link_id' => $link?->id,
                'type' => $type,
                'occurred_at' => $occurredAt ?? now(),
                'user_agent' => $userAgent === null ? null : Str::limit($userAgent, 1000, ''),
                'ip_hash' => $this->hashIpAddress($ipAddress),
                ...$classification,
                ...$location,
            ]);
        } catch (Throwable $exception) {
            report($exception);

            return null;
        }

        $this->dispatchEvent->handle($event);

        return $event;
    }

    private function validateLink(
        EmailDelivery $delivery,
        EmailTrackingEventType $type,
        ?EmailLink $link,
    ): void {
        if ($type === EmailTrackingEventType::Open && $link !== null) {
            throw new InvalidArgumentException('Open tracking events cannot reference a link.');
        }

        if ($type === EmailTrackingEventType::Click && $link === null) {
            throw new InvalidArgumentException('Click tracking events must reference a link.');
        }

        if ($link !== null && $link->email_id !== $delivery->email_id) {
            throw new InvalidArgumentException('The tracked link does not belong to the delivery campaign.');
        }
    }

    private function hashIpAddress(?string $ipAddress): ?string
    {
        if ($ipAddress === null || $ipAddress === '') {
            return null;
        }

        return hash_hmac('sha256', $ipAddress, (string) config('app.key'));
    }
}
