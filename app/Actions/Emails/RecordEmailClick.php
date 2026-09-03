<?php

namespace App\Actions\Emails;

use App\Models\EmailDelivery;
use App\Models\EmailLink;
use App\Models\EmailLinkClick;
use App\Models\EmailLinkTrackingAggregate;
use App\Models\EmailTrackingAggregate;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class RecordEmailClick
{
    /** @return array{open: bool, click: true} */
    public function handle(
        EmailDelivery $delivery,
        EmailLink $link,
        ?CarbonInterface $occurredAt = null,
    ): array {
        return DB::transaction(function () use ($delivery, $link, $occurredAt): array {
            $locked = EmailDelivery::query()->lockForUpdate()->findOrFail($delivery->id);
            $trackOpens = (bool) $locked->email()->value('track_opens');
            $occurredAt ??= now();
            $isUniqueClick = $locked->clicks_count === 0;
            $countsAsOpen = $trackOpens && $locked->opens_count === 0;
            $attributes = [
                'clicks_count' => $locked->clicks_count + 1,
                'first_clicked_at' => $this->earlier($locked->first_clicked_at, $occurredAt),
                'last_clicked_at' => $this->later($locked->last_clicked_at, $occurredAt),
            ];

            if ($countsAsOpen) {
                $attributes['opens_count'] = 1;
                $attributes['first_opened_at'] = $occurredAt;
                $attributes['last_opened_at'] = $occurredAt;
            }

            $locked->update($attributes);

            $click = EmailLinkClick::query()->firstOrNew([
                'email_delivery_id' => $locked->id,
                'email_link_id' => $link->id,
            ]);
            $isUniqueLinkClick = ! $click->exists;
            $click->fill([
                'clicks_count' => $click->exists ? $click->clicks_count + 1 : 1,
                'first_clicked_at' => $this->earlier($click->first_clicked_at, $occurredAt),
                'last_clicked_at' => $this->later($click->last_clicked_at, $occurredAt),
            ])->save();

            $aggregate = $this->lockEmailAggregate($locked->email_id);
            $aggregateAttributes = [
                'total_clicks_count' => $aggregate->total_clicks_count + 1,
                'unique_clicks_count' => $aggregate->unique_clicks_count + (int) $isUniqueClick,
                'revision' => $aggregate->revision + 1,
                'first_clicked_at' => $this->earlier($aggregate->first_clicked_at, $occurredAt),
                'last_clicked_at' => $this->later($aggregate->last_clicked_at, $occurredAt),
            ];

            if ($countsAsOpen) {
                $aggregateAttributes['total_opens_count'] = $aggregate->total_opens_count + 1;
                $aggregateAttributes['unique_opens_count'] = $aggregate->unique_opens_count + 1;
                $aggregateAttributes['first_opened_at'] = $this->earlier($aggregate->first_opened_at, $occurredAt);
                $aggregateAttributes['last_opened_at'] = $this->later($aggregate->last_opened_at, $occurredAt);
            }

            $aggregate->forceFill($aggregateAttributes)->save();

            $linkAggregate = $this->lockLinkAggregate($link->id);
            $linkAggregate->forceFill([
                'total_clicks_count' => $linkAggregate->total_clicks_count + 1,
                'unique_clicks_count' => $linkAggregate->unique_clicks_count + (int) $isUniqueLinkClick,
                'revision' => $linkAggregate->revision + 1,
                'first_clicked_at' => $this->earlier($linkAggregate->first_clicked_at, $occurredAt),
                'last_clicked_at' => $this->later($linkAggregate->last_clicked_at, $occurredAt),
            ])->save();

            return [
                'open' => $countsAsOpen,
                'click' => true,
            ];
        }, attempts: 3);
    }

    private function lockEmailAggregate(int $emailId): EmailTrackingAggregate
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

    private function lockLinkAggregate(int $linkId): EmailLinkTrackingAggregate
    {
        $now = now();

        EmailLinkTrackingAggregate::query()->insertOrIgnore([
            'email_link_id' => $linkId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return EmailLinkTrackingAggregate::query()
            ->where('email_link_id', $linkId)
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
