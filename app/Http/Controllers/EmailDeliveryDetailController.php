<?php

namespace App\Http\Controllers;

use App\Models\Email;
use App\Models\EmailDelivery;
use App\Models\EmailDeliveryAttempt;
use App\Models\EmailProviderEvent;
use App\Models\Team;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class EmailDeliveryDetailController extends Controller
{
    /**
     * Everything the report knows about one recipient: the delivery's
     * timeline, every send attempt, and the provider feedback events. The
     * raw event payload is never returned; it can hold other addresses.
     */
    public function __invoke(Team $currentTeam, Email $email, EmailDelivery $delivery): JsonResponse
    {
        Gate::authorize('view', $email);

        $attempts = $delivery->attempts()->latest('id')->get();
        $events = EmailProviderEvent::query()
            ->where('email_delivery_id', $delivery->id)
            ->orderBy('occurred_at')
            ->orderBy('id')
            ->get(['id', 'type', 'occurred_at', 'processed_at']);

        return response()->json([
            'uuid' => $delivery->uuid,
            'email' => $delivery->email_address,
            'name' => trim($delivery->first_name.' '.$delivery->last_name) ?: null,
            'status' => $delivery->status->value,
            'failure_reason' => $delivery->failure_reason,
            'failure_code' => $delivery->failure_code?->label(),
            'timeline' => array_values(array_filter([
                ['label' => __('Sent'), 'at' => $delivery->sent_at?->toISOString()],
                ['label' => __('Delivered'), 'at' => $delivery->delivered_at?->toISOString()],
                ['label' => __('Delayed'), 'at' => $delivery->delayed_at?->toISOString()],
                ['label' => __('Bounced'), 'at' => $delivery->bounced_at?->toISOString()],
                ['label' => __('Complained'), 'at' => $delivery->complained_at?->toISOString()],
                ['label' => __('First opened'), 'at' => $delivery->first_opened_at?->toISOString()],
                ['label' => __('First clicked'), 'at' => $delivery->first_clicked_at?->toISOString()],
            ], fn (array $entry): bool => $entry['at'] !== null)),
            'opens' => $delivery->opens_count,
            'clicks' => $delivery->clicks_count,
            'attempts' => $attempts->map(fn (EmailDeliveryAttempt $attempt): array => [
                'uuid' => $attempt->uuid,
                'provider' => $attempt->provider->label(),
                'connection' => $attempt->integration_name,
                'status' => $attempt->status->value,
                'failure_reason' => $attempt->failure_reason,
                'attempted_at' => $attempt->send_attempted_at?->toISOString(),
                'sent_at' => $attempt->sent_at?->toISOString(),
            ])->values(),
            'events' => $events->map(fn (EmailProviderEvent $event): array => [
                'type' => $event->type,
                'at' => ($event->occurred_at ?? $event->processed_at)->toISOString(),
            ])->values(),
        ]);
    }
}
