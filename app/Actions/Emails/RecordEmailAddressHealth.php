<?php

namespace App\Actions\Emails;

use App\Enums\EmailAddressHealthReason;
use App\Enums\EmailAddressHealthStatus;
use App\Enums\EmailProvider;
use App\Models\EmailAddressHealth;
use App\Models\Team;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RecordEmailAddressHealth
{
    /**
     * Temporary bounces in a row, with no delivery in between, after which an
     * address is treated as undeliverable for marketing mail. Mailbox
     * providers read repeated sends to a mailbox that keeps deferring as a
     * sign of a stale list.
     */
    public const int CONSECUTIVE_SOFT_BOUNCE_LIMIT = 3;

    /**
     * SES bounce subtypes that say something about the mailbox itself. A
     * message that was too large or had its content or attachment refused
     * says nothing about whether the next one will arrive.
     *
     * @var list<string>
     */
    public const array MAILBOX_SOFT_BOUNCES = ['General', 'MailboxFull'];

    /**
     * Whether the workspace must not send to the address. A run of temporary
     * bounces stops marketing mail only; transactional mail such as receipts
     * still goes, and its next delivery lifts that suppression.
     */
    public function isSuppressed(Team $team, string $email, bool $transactional = false): bool
    {
        return EmailAddressHealth::query()
            ->whereBelongsTo($team)
            ->where('email', $this->normalize($email))
            ->where('status', EmailAddressHealthStatus::Suppressed)
            ->when($transactional, fn ($query) => $query->where(
                fn ($query) => $query->whereNull('reason')
                    ->orWhere('reason', '!=', EmailAddressHealthReason::RepeatedTransientBounce),
            ))
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
            $liftsSoftBounceSuppression = $health->status === EmailAddressHealthStatus::Suppressed
                && $health->reason === EmailAddressHealthReason::RepeatedTransientBounce;

            if (($health->status !== EmailAddressHealthStatus::Suppressed || $liftsSoftBounceSuppression)
                && $this->isCurrent($health, $occurredAt)) {
                $health->forceFill([
                    'status' => EmailAddressHealthStatus::Deliverable,
                    'reason' => EmailAddressHealthReason::Delivered,
                    'provider' => $provider,
                    'detail' => null,
                    'suppressed_at' => $liftsSoftBounceSuppression ? null : $health->suppressed_at,
                ]);
            }

            // Bounces before this delivery no longer count toward a run, even
            // when its report arrives after a later bounce.
            $health->recent_soft_bounces = $this->bouncesAfter($health->recent_soft_bounces, $occurredAt);

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

            $countsTowardRun = ($detail === null || in_array($detail, self::MAILBOX_SOFT_BOUNCES, true))
                && ($health->last_delivered_at === null || $occurredAt->greaterThan($health->last_delivered_at));

            if ($countsTowardRun) {
                $health->recent_soft_bounces = collect([...($health->recent_soft_bounces ?? []), $occurredAt->toIso8601String()])
                    ->unique()
                    ->sort()
                    ->take(-self::CONSECUTIVE_SOFT_BOUNCE_LIMIT)
                    ->values()
                    ->all();
            }

            if ($health->status !== EmailAddressHealthStatus::Suppressed
                && count($health->recent_soft_bounces ?? []) >= self::CONSECUTIVE_SOFT_BOUNCE_LIMIT) {
                $health->forceFill([
                    'status' => EmailAddressHealthStatus::Suppressed,
                    'reason' => EmailAddressHealthReason::RepeatedTransientBounce,
                    'provider' => $provider,
                    'detail' => $detail,
                    'suppressed_at' => $occurredAt,
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

    /**
     * @param  list<string>|null  $bounces
     * @return list<string>
     */
    private function bouncesAfter(?array $bounces, CarbonInterface $deliveredAt): array
    {
        return array_values(array_filter(
            $bounces ?? [],
            fn (string $bouncedAt): bool => Carbon::parse($bouncedAt)->greaterThan($deliveredAt),
        ));
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
