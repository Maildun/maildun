<?php

namespace App\Actions\Transactional;

use App\Exceptions\EmailTransportException;
use App\Jobs\SendTransactionalEmailDelivery;
use App\Models\Team;
use App\Models\TeamApiKey;
use App\Models\TransactionalEmail;
use App\Models\TransactionalEmailDelivery;
use App\Services\ResolvedEmailTransport;
use App\Services\TeamMailer;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

class QueueTransactionalEmail
{
    public function __construct(
        private RenderTransactionalContent $renderer,
        private TeamMailer $teamMailer,
    ) {}

    /**
     * @param  array{to: string, data?: array<string, mixed>, idempotency_key?: string}  $payload
     * @return array{delivery: TransactionalEmailDelivery, created: bool}
     */
    public function handle(TeamApiKey $apiKey, TransactionalEmail $email, array $payload): array
    {
        $idempotencyKey = $payload['idempotency_key'] ?? null;
        $requestHash = hash('sha256', json_encode(Arr::sortRecursive([
            'transactional_email_id' => $email->id,
            'to' => $payload['to'],
            'data' => $payload['data'] ?? [],
        ]), JSON_THROW_ON_ERROR));

        if (is_string($idempotencyKey)) {
            $existing = $this->existingDelivery($apiKey, $idempotencyKey);

            if ($existing instanceof TransactionalEmailDelivery) {
                $this->ensureMatchingRequest($existing, $requestHash);

                return ['delivery' => $existing, 'created' => false];
            }
        }

        try {
            $transport = $this->teamMailer->resolve($apiKey->team);
        } catch (EmailTransportException) {
            throw new HttpResponseException(response()->json([
                'message' => __('Email delivery is disconnected for this workspace.'),
            ], 503));
        }

        // The workspace, not the API caller, owns the From address, so an
        // unauthorized sender is a workspace configuration fault.
        if (! $transport->allowsSender($email->resolvedFromAddress())) {
            throw new HttpResponseException(response()->json([
                'message' => __('The From address for this email is not authorized for the connected email provider.'),
            ], 503));
        }

        try {
            $delivery = $this->queueDelivery(
                $apiKey->team,
                $email,
                $payload,
                $transport,
                $apiKey,
                $idempotencyKey,
                $requestHash,
            );
        } catch (UniqueConstraintViolationException $exception) {
            if (! is_string($idempotencyKey)) {
                throw $exception;
            }

            $delivery = $this->existingDelivery($apiKey, $idempotencyKey);

            if (! $delivery instanceof TransactionalEmailDelivery) {
                throw $exception;
            }

            $this->ensureMatchingRequest($delivery, $requestHash);

            return ['delivery' => $delivery, 'created' => false];
        }

        return ['delivery' => $delivery, 'created' => true];
    }

    /**
     * Queue a transactional email initiated by the application rather than a
     * workspace API caller.
     *
     * @param  array{to: string, data?: array<string, mixed>}  $payload
     */
    public function handleForTeam(Team $team, TransactionalEmail $email, array $payload): TransactionalEmailDelivery
    {
        $transport = $this->teamMailer->resolve($team);

        if (! $transport->allowsSender($email->resolvedFromAddress())) {
            throw EmailTransportException::unauthorizedSender();
        }

        return $this->queueDelivery($team, $email, $payload, $transport);
    }

    /**
     * @param  array{to: string, data?: array<string, mixed>}  $payload
     */
    private function queueDelivery(
        Team $team,
        TransactionalEmail $email,
        array $payload,
        ResolvedEmailTransport $transport,
        ?TeamApiKey $apiKey = null,
        ?string $idempotencyKey = null,
        ?string $requestHash = null,
    ): TransactionalEmailDelivery {
        // The stored body is what goes on the wire, so the browser copy link
        // has to be known before rendering; that means fixing the uuid first.
        $uuid = (string) Str::uuid();

        /** @var array<string, mixed> $data */
        $data = [
            ...($payload['data'] ?? []),
            // Last so a caller-supplied key cannot shadow the link.
            'web_view_url' => URL::signedRoute('public.web_view.transactional.show', ['delivery' => $uuid]),
        ];

        $delivery = $team->transactionalEmailDeliveries()->make([
            'transactional_email_id' => $email->id,
            'team_api_key_id' => $apiKey?->id,
            'idempotency_key' => $idempotencyKey,
            'request_hash' => $requestHash,
            'to_address' => $payload['to'],
            'subject' => $this->renderer->text($email->subject, $data),
            'html' => $this->renderer->html($email->html ?? '', $data),
            'from_name' => $email->resolvedFromName(),
            'from_address' => $email->resolvedFromAddress(),
            'reply_to' => $email->resolvedReplyTo(),
            'provider' => $transport->provider->value,
            'uses_team_email_integration' => $transport->usesTeamEmailIntegration(),
        ]);
        $delivery->uuid = $uuid;
        $delivery->save();

        SendTransactionalEmailDelivery::dispatch($delivery->id)->afterCommit();

        return $delivery;
    }

    private function existingDelivery(TeamApiKey $apiKey, string $idempotencyKey): ?TransactionalEmailDelivery
    {
        return $apiKey->team->transactionalEmailDeliveries()
            ->where('idempotency_key', $idempotencyKey)
            ->first();
    }

    private function ensureMatchingRequest(TransactionalEmailDelivery $delivery, string $requestHash): void
    {
        if (hash_equals((string) $delivery->request_hash, $requestHash)) {
            return;
        }

        throw new HttpResponseException(response()->json([
            'message' => __('The Idempotency-Key has already been used with a different request.'),
        ], 409));
    }
}
