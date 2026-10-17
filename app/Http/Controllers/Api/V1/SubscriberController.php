<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Audiences\SubscribeViaApi;
use App\Actions\Audiences\UnsubscribeViaApi;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\SubscribeSubscriberRequest;
use App\Http\Requests\Api\UnsubscribeSubscriberRequest;
use App\Models\Subscriber;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;

class SubscriberController extends Controller
{
    public function store(SubscribeSubscriberRequest $request, SubscribeViaApi $subscribe): JsonResponse
    {
        /** @var array{email: string, first_name?: string|null, last_name?: string|null, language?: string|null, consent_text?: string|null, attributes?: array<string, string|int|float|null>} $data */
        $data = Arr::except($request->validated(), 'consent_ip');

        // The caller is usually a server acting for the person who consented,
        // so it may pass that person's address; otherwise the caller's is kept.
        $consentIp = $request->validated('consent_ip') ?? $request->ip();
        $subscriber = $subscribe->handle($request->audience(), $data, $consentIp);

        return response()->json([
            'data' => $this->subscriberPayload($subscriber),
        ]);
    }

    public function destroy(UnsubscribeSubscriberRequest $request, UnsubscribeViaApi $unsubscribe): JsonResponse
    {
        $subscriber = $unsubscribe->handle(
            $request->audience(),
            $request->string('email')->value(),
        );

        return response()->json([
            'data' => [
                'id' => $subscriber?->uuid,
                'email' => $request->string('email')->value(),
                'status' => 'unsubscribed',
                'unsubscribed_at' => $subscriber?->unsubscribed_at?->toISOString(),
            ],
        ]);
    }

    /** @return array{id: string, email: string, first_name: string|null, last_name: string|null, language: string|null, status: string, subscribed_at: string|null} */
    private function subscriberPayload(Subscriber $subscriber): array
    {
        return [
            'id' => $subscriber->uuid,
            'email' => $subscriber->email,
            'first_name' => $subscriber->first_name,
            'last_name' => $subscriber->last_name,
            'language' => $subscriber->language?->value,
            'status' => $subscriber->status->value,
            'subscribed_at' => $subscriber->subscribed_at?->toISOString(),
        ];
    }
}
