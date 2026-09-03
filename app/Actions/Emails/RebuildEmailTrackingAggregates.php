<?php

namespace App\Actions\Emails;

use App\Models\Email;
use App\Models\EmailLink;
use App\Models\EmailLinkTrackingAggregate;
use App\Models\EmailTrackingAggregate;
use RuntimeException;

class RebuildEmailTrackingAggregates
{
    private const int MAX_REBUILD_ATTEMPTS = 5;

    public function handle(Email $email): void
    {
        $this->rebuildEmailAggregate($email);

        $email->links()
            ->select(['id'])
            ->orderBy('id')
            ->eachById(fn (EmailLink $link) => $this->rebuildLinkAggregate($link), 100);
    }

    private function rebuildEmailAggregate(Email $email): void
    {
        for ($attempt = 1; $attempt <= self::MAX_REBUILD_ATTEMPTS; $attempt++) {
            $aggregate = $this->emailAggregate($email->id);
            $deliveryStats = $email->deliveries()
                ->toBase()
                ->selectRaw('COALESCE(SUM(opens_count), 0) as total_opens_count')
                ->selectRaw('COALESCE(SUM(CASE WHEN opens_count > 0 THEN 1 ELSE 0 END), 0) as unique_opens_count')
                ->selectRaw('COALESCE(SUM(clicks_count), 0) as total_clicks_count')
                ->selectRaw('COALESCE(SUM(CASE WHEN clicks_count > 0 THEN 1 ELSE 0 END), 0) as unique_clicks_count')
                ->selectRaw('MIN(first_opened_at) as first_opened_at')
                ->selectRaw('MAX(last_opened_at) as last_opened_at')
                ->selectRaw('MIN(first_clicked_at) as first_clicked_at')
                ->selectRaw('MAX(last_clicked_at) as last_clicked_at')
                ->first();

            $updated = EmailTrackingAggregate::query()
                ->whereKey($aggregate->id)
                ->where('revision', $aggregate->revision)
                ->update([
                    'total_opens_count' => (int) ($deliveryStats->total_opens_count ?? 0),
                    'unique_opens_count' => (int) ($deliveryStats->unique_opens_count ?? 0),
                    'total_clicks_count' => (int) ($deliveryStats->total_clicks_count ?? 0),
                    'unique_clicks_count' => (int) ($deliveryStats->unique_clicks_count ?? 0),
                    'revision' => $aggregate->revision + 1,
                    'first_opened_at' => $deliveryStats->first_opened_at ?? null,
                    'last_opened_at' => $deliveryStats->last_opened_at ?? null,
                    'first_clicked_at' => $deliveryStats->first_clicked_at ?? null,
                    'last_clicked_at' => $deliveryStats->last_clicked_at ?? null,
                ]);

            if ($updated === 1) {
                return;
            }
        }

        throw new RuntimeException('The campaign tracking aggregate changed too frequently to rebuild.');
    }

    private function rebuildLinkAggregate(EmailLink $link): void
    {
        for ($attempt = 1; $attempt <= self::MAX_REBUILD_ATTEMPTS; $attempt++) {
            $aggregate = $this->linkAggregate($link->id);
            $clickStats = $link->clicks()
                ->toBase()
                ->selectRaw('COALESCE(SUM(clicks_count), 0) as total_clicks_count')
                ->selectRaw('COUNT(*) as unique_clicks_count')
                ->selectRaw('MIN(first_clicked_at) as first_clicked_at')
                ->selectRaw('MAX(last_clicked_at) as last_clicked_at')
                ->first();

            $updated = EmailLinkTrackingAggregate::query()
                ->whereKey($aggregate->id)
                ->where('revision', $aggregate->revision)
                ->update([
                    'total_clicks_count' => (int) ($clickStats->total_clicks_count ?? 0),
                    'unique_clicks_count' => (int) ($clickStats->unique_clicks_count ?? 0),
                    'revision' => $aggregate->revision + 1,
                    'first_clicked_at' => $clickStats->first_clicked_at ?? null,
                    'last_clicked_at' => $clickStats->last_clicked_at ?? null,
                ]);

            if ($updated === 1) {
                return;
            }
        }

        throw new RuntimeException('The link tracking aggregate changed too frequently to rebuild.');
    }

    private function emailAggregate(int $emailId): EmailTrackingAggregate
    {
        $now = now();

        EmailTrackingAggregate::query()->insertOrIgnore([
            'email_id' => $emailId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return EmailTrackingAggregate::query()->where('email_id', $emailId)->firstOrFail();
    }

    private function linkAggregate(int $linkId): EmailLinkTrackingAggregate
    {
        $now = now();

        EmailLinkTrackingAggregate::query()->insertOrIgnore([
            'email_link_id' => $linkId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return EmailLinkTrackingAggregate::query()->where('email_link_id', $linkId)->firstOrFail();
    }
}
