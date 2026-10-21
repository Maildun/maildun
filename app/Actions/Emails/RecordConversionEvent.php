<?php

namespace App\Actions\Emails;

use App\Models\CampaignSeries;
use App\Models\EmailConversionEvent;
use App\Models\Team;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Accept a conversion reported by an external system, once per external event
 * ID in a workspace, and persist its attribution with it.
 */
class RecordConversionEvent
{
    public function __construct(private AttributeConversionEvent $attribution) {}

    /**
     * @param  array{event_id: string, event: string, email: string, occurred_at?: string|null, value_minor?: int|null, currency?: string|null, campaign_series?: string|null, metadata?: array<array-key, mixed>|null}  $payload
     * @return array{event: EmailConversionEvent, created: bool}
     */
    public function handle(Team $team, array $payload, ?CampaignSeries $explicitSeries): array
    {
        $occurredAt = isset($payload['occurred_at'])
            ? CarbonImmutable::parse($payload['occurred_at'])->utc()
            : null;
        $requestHash = $this->requestHash($payload, $occurredAt);
        $existing = $this->existingEvent($team, $payload['event_id']);

        if ($existing instanceof EmailConversionEvent) {
            return ['event' => $this->replay($existing, $requestHash), 'created' => false];
        }

        $occurredAt ??= CarbonImmutable::now()->utc();
        $attribution = $this->attribution->handle(
            $team,
            $payload['event'],
            $payload['email'],
            $occurredAt,
            $explicitSeries,
        );

        try {
            $event = DB::transaction(fn (): EmailConversionEvent => $team->conversionEvents()->create([
                'external_id' => $payload['event_id'],
                'request_hash' => $requestHash,
                'event_key' => $payload['event'],
                'email' => $payload['email'],
                'requested_campaign_series_id' => $explicitSeries?->id,
                'value_minor' => $payload['value_minor'] ?? null,
                'currency' => $payload['currency'] ?? null,
                'metadata' => $payload['metadata'] ?? null,
                'occurred_at' => $occurredAt,
                ...$attribution,
            ]));
        } catch (UniqueConstraintViolationException $exception) {
            // A concurrent request with the same event ID committed first.
            $existing = $this->existingEvent($team, $payload['event_id']);

            if (! $existing instanceof EmailConversionEvent) {
                throw $exception;
            }

            return ['event' => $this->replay($existing, $requestHash), 'created' => false];
        }

        Log::info('Conversion event recorded.', [
            'team_id' => $team->id,
            'conversion_event' => $event->uuid,
            'attribution_type' => $event->attribution_type->value,
        ]);

        return ['event' => $event, 'created' => true];
    }

    private function existingEvent(Team $team, string $externalId): ?EmailConversionEvent
    {
        return $team->conversionEvents()->where('external_id', $externalId)->first();
    }

    private function replay(EmailConversionEvent $event, string $requestHash): EmailConversionEvent
    {
        if (hash_equals($event->request_hash, $requestHash)) {
            Log::info('Duplicate conversion event replayed.', [
                'team_id' => $event->team_id,
                'conversion_event' => $event->uuid,
            ]);

            return $event;
        }

        Log::notice('Conversion event ID reused with different data.', [
            'team_id' => $event->team_id,
            'conversion_event' => $event->uuid,
        ]);

        throw new HttpResponseException(response()->json([
            'message' => __('This event_id has already been used with different data.'),
            'code' => 'idempotency_conflict',
        ], 409));
    }

    /**
     * A fingerprint of everything the caller sent, so a retry can be told apart
     * from a different event reusing the same ID. An omitted occurred_at stays
     * omitted, because the time it defaults to differs on every retry.
     *
     * @param  array<string, mixed>  $payload
     */
    private function requestHash(array $payload, ?CarbonImmutable $occurredAt): string
    {
        return hash('sha256', json_encode(Arr::sortRecursive([
            'event' => $payload['event'],
            'email' => $payload['email'],
            'occurred_at' => $occurredAt?->toIso8601ZuluString('microsecond'),
            'value_minor' => $payload['value_minor'] ?? null,
            'currency' => $payload['currency'] ?? null,
            'campaign_series' => $payload['campaign_series'] ?? null,
            'metadata' => $payload['metadata'] ?? null,
        ]), JSON_THROW_ON_ERROR));
    }
}
