<?php

namespace App\Actions\Emails;

use App\Enums\EmailAddressHealthReason;
use App\Enums\EmailAddressHealthStatus;
use App\Enums\EmailProvider;
use App\Models\EmailAddressHealth;
use App\Models\Team;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RecordEmailAddressHealth
{
    public function isSuppressed(Team $team, string $email): bool
    {
        return EmailAddressHealth::query()
            ->whereBelongsTo($team)
            ->where('email', $this->normalize($email))
            ->where('status', EmailAddressHealthStatus::Suppressed)
            ->exists();
    }

    public function recordDelivered(
        Team $team,
        string $email,
        EmailProvider $provider,
        CarbonInterface $occurredAt,
    ): EmailAddressHealth {
        return DB::transaction(function () use ($team, $email, $provider, $occurredAt): EmailAddressHealth {
            $health = $this->healthForUpdate($team, $email);

            if ($health->status !== EmailAddressHealthStatus::Suppressed
                && $this->isCurrent($health, $occurredAt)) {
                $health->forceFill([
                    'status' => EmailAddressHealthStatus::Deliverable,
                    'reason' => EmailAddressHealthReason::Delivered,
                    'provider' => $provider,
                    'detail' => null,
                ]);
            }

            $health->forceFill([
                'last_event_at' => $this->latest($health->last_event_at, $occurredAt),
                'last_delivered_at' => $this->latest($health->last_delivered_at, $occurredAt),
            ])->save();

            return $health;
        }, attempts: 3);
    }

    public function recordTransientBounce(
        Team $team,
        string $email,
        EmailProvider $provider,
        CarbonInterface $occurredAt,
        ?string $detail = null,
    ): EmailAddressHealth {
        return DB::transaction(function () use ($team, $email, $provider, $occurredAt, $detail): EmailAddressHealth {
            $health = $this->healthForUpdate($team, $email);

            if ($health->status !== EmailAddressHealthStatus::Suppressed
                && $this->isCurrent($health, $occurredAt)) {
                $health->forceFill([
                    'status' => EmailAddressHealthStatus::Risky,
                    'reason' => EmailAddressHealthReason::TransientBounce,
                    'provider' => $provider,
                    'detail' => $detail,
                ]);
            }

            $health->forceFill([
                'soft_bounce_count' => $health->soft_bounce_count + 1,
                'last_event_at' => $this->latest($health->last_event_at, $occurredAt),
                'last_failed_at' => $this->latest($health->last_failed_at, $occurredAt),
            ])->save();

            return $health;
        }, attempts: 3);
    }

    public function recordPermanentBounce(
        Team $team,
        string $email,
        EmailProvider $provider,
        CarbonInterface $occurredAt,
        ?string $detail = null,
    ): EmailAddressHealth {
        return $this->recordSuppression(
            $team,
            $email,
            $provider,
            $occurredAt,
            EmailAddressHealthReason::PermanentBounce,
            'hard_bounce_count',
            $detail,
        );
    }

    public function recordComplaint(
        Team $team,
        string $email,
        EmailProvider $provider,
        CarbonInterface $occurredAt,
    ): EmailAddressHealth {
        return $this->recordSuppression(
            $team,
            $email,
            $provider,
            $occurredAt,
            EmailAddressHealthReason::Complaint,
            'complaint_count',
        );
    }

    public function recordFailure(
        Team $team,
        string $email,
        EmailProvider $provider,
        string $detail,
        ?CarbonInterface $occurredAt = null,
    ): EmailAddressHealth {
        $occurredAt ??= now();

        return DB::transaction(function () use ($team, $email, $provider, $detail, $occurredAt): EmailAddressHealth {
            $health = $this->healthForUpdate($team, $email);

            if ($health->status === EmailAddressHealthStatus::Unknown
                && $this->isCurrent($health, $occurredAt)) {
                $health->forceFill([
                    'reason' => EmailAddressHealthReason::SendFailure,
                    'provider' => $provider,
                    'detail' => $detail,
                ]);
            }

            $health->forceFill([
                'failure_count' => $health->failure_count + 1,
                'last_event_at' => $this->latest($health->last_event_at, $occurredAt),
                'last_failed_at' => $this->latest($health->last_failed_at, $occurredAt),
            ])->save();

            return $health;
        }, attempts: 3);
    }

    private function recordSuppression(
        Team $team,
        string $email,
        EmailProvider $provider,
        CarbonInterface $occurredAt,
        EmailAddressHealthReason $reason,
        string $counter,
        ?string $detail = null,
    ): EmailAddressHealth {
        return DB::transaction(function () use ($team, $email, $provider, $occurredAt, $reason, $counter, $detail): EmailAddressHealth {
            $health = $this->healthForUpdate($team, $email);

            $health->forceFill([
                'status' => EmailAddressHealthStatus::Suppressed,
                'reason' => $reason,
                'provider' => $provider,
                'detail' => $detail,
                $counter => (int) $health->getAttribute($counter) + 1,
                'last_event_at' => $this->latest($health->last_event_at, $occurredAt),
                'last_failed_at' => $this->latest($health->last_failed_at, $occurredAt),
                'suppressed_at' => $health->suppressed_at ?? $occurredAt,
            ])->save();

            return $health;
        }, attempts: 3);
    }

    private function healthForUpdate(Team $team, string $email): EmailAddressHealth
    {
        $normalized = $this->normalize($email);

        EmailAddressHealth::query()->createOrFirst([
            'team_id' => $team->id,
            'email' => $normalized,
        ], [
            'uuid' => (string) Str::uuid(),
        ]);

        return EmailAddressHealth::query()
            ->whereBelongsTo($team)
            ->where('email', $normalized)
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function normalize(string $email): string
    {
        return Str::lower(trim($email));
    }

    private function isCurrent(EmailAddressHealth $health, CarbonInterface $occurredAt): bool
    {
        return $health->last_event_at === null || $occurredAt->greaterThanOrEqualTo($health->last_event_at);
    }

    private function latest(?CarbonInterface $current, CarbonInterface $candidate): CarbonInterface
    {
        return $current !== null && $current->greaterThan($candidate) ? $current : $candidate;
    }
}
