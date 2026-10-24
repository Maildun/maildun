<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Automation unsubscribe links name the subscriber's uuid. Deleting the
     * member nulls subscriber_id here, so the delivery also keeps that uuid,
     * the audience and the contact: an older link can then still find the
     * person's current membership in the same audience.
     */
    public function up(): void
    {
        Schema::table('automation_email_deliveries', function (Blueprint $table): void {
            $table->uuid('subscriber_uuid')->nullable()->after('subscriber_id')->index();
            $table->foreignId('audience_id')->nullable()->after('subscriber_uuid')->constrained()->nullOnDelete();
            $table->foreignId('contact_id')->nullable()->after('audience_id')->constrained()->nullOnDelete();
        });

        DB::table('automation_email_deliveries')
            ->whereNull('subscriber_uuid')
            ->whereNotNull('subscriber_id')
            ->orderBy('id')
            ->chunkById(100, function (Collection $deliveries): void {
                foreach ($deliveries as $delivery) {
                    $subscriber = DB::table('subscribers')
                        ->where('id', $delivery->subscriber_id)
                        ->first(['uuid', 'audience_id', 'contact_id']);

                    if ($subscriber !== null) {
                        DB::table('automation_email_deliveries')
                            ->where('id', $delivery->id)
                            ->update([
                                'subscriber_uuid' => $subscriber->uuid,
                                'audience_id' => $subscriber->audience_id,
                                'contact_id' => $subscriber->contact_id,
                            ]);
                    }
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('automation_email_deliveries', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('contact_id');
            $table->dropConstrainedForeignId('audience_id');
            $table->dropIndex(['subscriber_uuid']);
            $table->dropColumn('subscriber_uuid');
        });
    }
};
