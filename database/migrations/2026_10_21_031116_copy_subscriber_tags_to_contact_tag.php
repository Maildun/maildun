<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Copy tags that v0.1.0-beta stored on subscriber_tag onto the contact of
     * each membership. Safe to re-run: existing contact_tag rows are kept and
     * tags from another workspace are skipped. subscriber_tag is left in place.
     */
    public function up(): void
    {
        DB::table('subscriber_tag')
            ->join('subscribers', 'subscribers.id', '=', 'subscriber_tag.subscriber_id')
            ->join('contacts', 'contacts.id', '=', 'subscribers.contact_id')
            ->join('tags', 'tags.id', '=', 'subscriber_tag.tag_id')
            ->whereColumn('tags.team_id', 'contacts.team_id')
            ->orderBy('subscriber_tag.id')
            ->select([
                'subscriber_tag.id',
                'subscribers.contact_id',
                'subscriber_tag.tag_id',
                'subscriber_tag.created_at',
                'subscriber_tag.updated_at',
            ])
            ->chunkById(500, function (Collection $subscriberTags): void {
                DB::table('contact_tag')->insertOrIgnore($subscriberTags
                    ->unique(fn (object $subscriberTag): string => $subscriberTag->contact_id.':'.$subscriberTag->tag_id)
                    ->map(fn (object $subscriberTag): array => [
                        'contact_id' => $subscriberTag->contact_id,
                        'tag_id' => $subscriberTag->tag_id,
                        'created_at' => $subscriberTag->created_at,
                        'updated_at' => $subscriberTag->updated_at,
                    ])
                    ->values()
                    ->all());
            }, 'subscriber_tag.id', 'id');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Copied contact tags are a durable data backfill and cannot be safely told apart from tags added later.
    }
};
