<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Emails\RecordConversionEvent;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreConversionEventRequest;
use App\Http\Resources\Api\V1\ConversionEventResource;
use App\Models\Team;
use Illuminate\Http\JsonResponse;

class ConversionEventController extends Controller
{
    /**
     * Record a sales conversion. A retry with the same event_id and data
     * returns the stored event with 200 instead of 201.
     */
    public function store(StoreConversionEventRequest $request, RecordConversionEvent $recordConversionEvent): JsonResponse
    {
        $team = $request->attributes->get('team');
        abort_unless($team instanceof Team, 401);

        $validated = $request->validated();
        $payload = [
            'event_id' => (string) $validated['event_id'],
            'event' => (string) $validated['event'],
            'email' => (string) $validated['email'],
            'occurred_at' => isset($validated['occurred_at']) ? (string) $validated['occurred_at'] : null,
            'value_minor' => isset($validated['value_minor']) ? (int) $validated['value_minor'] : null,
            'currency' => isset($validated['currency']) ? (string) $validated['currency'] : null,
            'campaign_series' => $request->campaignSeries()?->uuid,
            'metadata' => is_array($validated['metadata'] ?? null) ? $validated['metadata'] : null,
        ];

        $result = $recordConversionEvent->handle($team, $payload, $request->campaignSeries());
        $result['event']->loadMissing([
            'campaignSeries:id,uuid',
            'campaign:id,uuid',
            'delivery:id,uuid',
        ]);

        return (new ConversionEventResource($result['event']))
            ->additional(['meta' => ['idempotent_replay' => ! $result['created']]])
            ->response()
            ->setStatusCode($result['created'] ? 201 : 200);
    }
}
