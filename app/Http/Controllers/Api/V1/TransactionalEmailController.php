<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Transactional\QueueTransactionalEmail;
use App\Enums\TransactionalEmailStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\SendTransactionalEmailRequest;
use App\Models\Team;
use App\Models\TeamApiKey;
use App\Models\TransactionalEmail;
use App\Models\TransactionalEmailDelivery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

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
            ->first();

        if (! $email instanceof TransactionalEmail) {
            return response()->json([
                'message' => __('No published transactional email uses this identifier.'),
                'code' => 'transactional_email_not_found',
            ], 404);
        }

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

    /**
     * Where one queued message stands. Scoped to the authenticated workspace;
     * the rendered body is never returned.
     */
    public function show(Request $request, string $delivery): JsonResponse
    {
        $team = $request->attributes->get('team');
        abort_unless($team instanceof Team, 401);

        // Postgres rejects a non-uuid value for a uuid column, so check first.
        $record = Str::isUuid($delivery)
            ? $team->transactionalEmailDeliveries()
                ->with(['transactionalEmail' => fn ($query) => $query->withTrashed()->select(['id', 'slug'])])
                ->where('uuid', $delivery)
                ->first()
            : null;

        if (! $record instanceof TransactionalEmailDelivery) {
            return response()->json([
                'message' => __('No message with this id exists in this workspace.'),
                'code' => 'delivery_not_found',
            ], 404);
        }

        return response()->json([
            'data' => [
                'id' => $record->uuid,
                'status' => $record->status->value,
                'transactional_email' => $record->transactionalEmail->slug,
                'to' => $record->to_address,
                'failure_reason' => $record->failure_reason,
                'created_at' => $record->created_at?->toISOString(),
                'sent_at' => $record->sent_at?->toISOString(),
            ],
        ]);
    }
}
