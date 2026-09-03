<?php

namespace App\Actions\Emails;

use App\Models\EmailDelivery;
use App\Models\EmailTrackingAggregate;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class RecordEmailOpen
{
    public function handle(EmailDelivery $delivery, ?CarbonInterface $occurredAt = null): bool
    {
        return DB::transaction(function () use ($delivery, $occurredAt): bool {
            $locked = EmailDelivery::query()->lockForUpdate()->findOrFail($delivery->id);

            if (! $locked->email()->value('track_opens')) {
                return false;
            }

            $occurredAt ??= now();
            $isUniqueOpen = $locked->opens_count === 0;

            $locked->update([
                'opens_count' => $locked->opens_count + 1,
                'first_opened_at' => $this->earlier($locked->first_opened_at, $occurredAt),
                'last_opened_at' => $this->later($locked->last_opened_at, $occurredAt),
            ]);

            $aggregate = $this->lockAggregate($locked->email_id);
            $aggregate->forceFill([
                'total_opens_count' => $aggregate->total_opens_count + 1,
                'unique_opens_count' => $aggregate->unique_opens_count + (int) $isUniqueOpen,
                'revision' => $aggregate->revision + 1,
                'first_opened_at' => $this->earlier($aggregate->first_opened_at, $occurredAt),
                'last_opened_at' => $this->later($aggregate->last_opened_at, $occurredAt),
            ])->save();

            return true;
        }, attempts: 3);
    }

    private function lockAggregate(int $emailId): EmailTrackingAggregate
    {
        $now = now();

        EmailTrackingAggregate::query()->insertOrIgnore([
            'email_id' => $emailId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return EmailTrackingAggregate::query()
            ->where('email_id', $emailId)
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function earlier(?CarbonInterface $current, CarbonInterface $candidate): CarbonInterface
    {
        return $current === null || $candidate->lessThan($current) ? $candidate : $current;
    }

    private function later(?CarbonInterface $current, CarbonInterface $candidate): CarbonInterface
    {
        return $current === null || $candidate->greaterThan($current) ? $candidate : $current;
    }
}
