<?php

namespace App\Http\Controllers;

use App\Actions\Automations\ResolveAutomationTriggerSubscriber;
use App\Actions\Automations\StartAutomationRun;
use App\Http\Requests\TriggerAutomationRequest;
use App\Models\Automation;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class AutomationTriggerController extends Controller
{
    public function store(
        TriggerAutomationRequest $request,
        Automation $automation,
        ResolveAutomationTriggerSubscriber $resolveSubscriber,
        StartAutomationRun $start,
    ): JsonResponse {
        $payload = $request->validated();
        $subscriber = $resolveSubscriber->handle($automation, $payload);

        $run = $start->handle($automation, $subscriber, [
            'source' => 'api',
            'data' => $payload['data'] ?? [],
        ]);

        if ($run === null) {
            return response()->json([
                'message' => __('This subscriber already has an open run for this automation.'),
            ], Response::HTTP_CONFLICT);
        }

        return response()->json([
            'run' => $run->uuid,
            'status' => $run->status->value,
            'automation' => $automation->uuid,
            'subscriber' => $subscriber->uuid,
        ], Response::HTTP_ACCEPTED);
    }
}
