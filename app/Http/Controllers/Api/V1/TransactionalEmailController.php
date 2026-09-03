<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Transactional\QueueTransactionalEmail;
use App\Enums\TransactionalEmailStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\SendTransactionalEmailRequest;
use App\Models\Team;
use App\Models\TeamApiKey;
use Illuminate\Http\JsonResponse;

class TransactionalEmailController extends Controller
{
    public function store(
        SendTransactionalEmailRequest $request,
        string $transactionalEmail,
        QueueTransactionalEmail $queueTransactionalEmail,
    ): JsonResponse {
        $team = $request->attributes->get('team');
        $apiKey = $request->attributes->get('teamApiKey');
        abort_unless($team instanceof Team && $apiKey instanceof TeamApiKey, 401);

        $email = $team->transactionalEmails()
            ->where('slug', $transactionalEmail)
            ->where('status', TransactionalEmailStatus::Published)
            ->firstOrFail();

        /** @var array{to: string, data?: array<string, mixed>, idempotency_key?: string} $payload */
        $payload = $request->validated();
        $result = $queueTransactionalEmail->handle($apiKey, $email, $payload);
        $delivery = $result['delivery'];

        return response()->json([
            'data' => [
                'id' => $delivery->uuid,
                'status' => $delivery->status->value,
                'transactional_email' => $email->slug,
                'to' => $delivery->to_address,
                'created_at' => $delivery->created_at?->toISOString(),
            ],
            'meta' => [
                'idempotent_replay' => ! $result['created'],
            ],
        ], 202);
    }
}
