<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The index names are explicit because the defaults reach Postgres's
     * 63-character identifier limit. Older deliveries keep a null step.
     */
    public function up(): void
    {
        Schema::table('automation_email_deliveries', function (Blueprint $table) {
            $table->foreignId('automation_run_step_id')
                ->nullable()
                ->after('automation_run_id')
                ->constrained()
                ->nullOnDelete();
            $table->index(['automation_run_step_id', 'status'], 'automation_deliveries_step_status_index');
            $table->index(
                ['status', 'send_attempted_at', 'updated_at'],
                'automation_deliveries_recovery_index',
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('automation_email_deliveries', function (Blueprint $table) {
            $table->dropIndex('automation_deliveries_recovery_index');
            $table->dropIndex('automation_deliveries_step_status_index');
            $table->dropConstrainedForeignId('automation_run_step_id');
        });
    }
};
