<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Deleting a member deletes their automation runs. Their deliveries now
     * stay behind with a null run, so the unsubscribe links already sent can
     * still find the audience the person was mailed from.
     */
    public function up(): void
    {
        Schema::table('automation_email_deliveries', function (Blueprint $table): void {
            $table->dropForeign(['automation_run_id']);
        });

        Schema::table('automation_email_deliveries', function (Blueprint $table): void {
            $table->foreignId('automation_run_id')->nullable()->change();
            $table->foreign('automation_run_id')->references('id')->on('automation_runs')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::table('automation_email_deliveries')->whereNull('automation_run_id')->exists()) {
            throw new RuntimeException('Deliveries kept after their run prevent a safe rollback. Apply a forward migration instead.');
        }

        Schema::table('automation_email_deliveries', function (Blueprint $table): void {
            $table->dropForeign(['automation_run_id']);
        });

        Schema::table('automation_email_deliveries', function (Blueprint $table): void {
            $table->foreignId('automation_run_id')->nullable(false)->change();
            $table->foreign('automation_run_id')->references('id')->on('automation_runs')->cascadeOnDelete();
        });
    }
};
