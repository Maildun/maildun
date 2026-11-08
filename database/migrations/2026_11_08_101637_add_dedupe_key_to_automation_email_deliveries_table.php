<?php

use App\Enums\EmailDeliveryStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Runs started by one tagging of a contact (one per audience membership)
     * share a dedupe key per send step. Only one of their deliveries may be
     * queued, sending or sent at a time; one that was cancelled, rejected or
     * failed never reached the contact and frees the key again.
     */
    public function up(): void
    {
        Schema::table('automation_email_deliveries', function (Blueprint $table): void {
            $table->char('dedupe_key', 64)->nullable()->after('automation_run_step_id');
        });

        $released = implode(', ', array_map(
            fn (EmailDeliveryStatus $status): string => "'{$status->value}'",
            [EmailDeliveryStatus::Cancelled, EmailDeliveryStatus::Rejected, EmailDeliveryStatus::Failed],
        ));

        // Partial index predicates take no bindings, so the statuses are inlined.
        DB::statement(
            "create unique index automation_deliveries_dedupe_key_unique on automation_email_deliveries (dedupe_key) where dedupe_key is not null and status not in ({$released})"
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('drop index if exists automation_deliveries_dedupe_key_unique');

        Schema::table('automation_email_deliveries', function (Blueprint $table): void {
            $table->dropColumn('dedupe_key');
        });
    }
};
