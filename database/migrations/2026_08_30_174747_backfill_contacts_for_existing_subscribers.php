<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('subscribers')
            ->join('audiences', 'audiences.id', '=', 'subscribers.audience_id')
            ->whereNull('subscribers.contact_id')
            ->orderBy('subscribers.id')
            ->select([
                'subscribers.id',
                'subscribers.email',
                'subscribers.first_name',
                'subscribers.last_name',
                'subscribers.created_at',
                'subscribers.updated_at',
                'audiences.team_id',
            ])
            ->chunkById(100, function (Collection $subscribers): void {
                foreach ($subscribers as $subscriber) {
                    $email = Str::lower(trim($subscriber->email));
                    $contactId = DB::table('contacts')
                        ->where('team_id', $subscriber->team_id)
                        ->where('email', $email)
                        ->value('id');

                    if ($contactId === null) {
                        $contactId = DB::table('contacts')->insertGetId([
                            'uuid' => (string) Str::uuid(),
                            'team_id' => $subscriber->team_id,
                            'email' => $email,
                            'first_name' => $subscriber->first_name,
                            'last_name' => $subscriber->last_name,
                            'created_at' => $subscriber->created_at,
                            'updated_at' => $subscriber->updated_at,
                        ]);
                    }

                    DB::table('subscribers')
                        ->where('id', $subscriber->id)
                        ->update(['contact_id' => $contactId]);
                }
            }, 'subscribers.id', 'id');

        DB::table('email_deliveries')
            ->whereNull('contact_id')
            ->whereNotNull('subscriber_id')
            ->orderBy('id')
            ->chunkById(100, function (Collection $deliveries): void {
                foreach ($deliveries as $delivery) {
                    $contactId = DB::table('subscribers')
                        ->where('id', $delivery->subscriber_id)
                        ->value('contact_id');

                    if ($contactId !== null) {
                        DB::table('email_deliveries')
                            ->where('id', $delivery->id)
                            ->whereNull('contact_id')
                            ->update(['contact_id' => $contactId]);
                    }
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Contact links are a durable data backfill and cannot be safely removed.
    }
};
