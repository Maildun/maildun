<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * automations:resume only re-queues pending and due branches that have
     * not moved for a while, and fails running ones that stopped reporting;
     * both look steps up by status and updated_at.
     */
    public function up(): void
    {
        Schema::table('automation_run_steps', function (Blueprint $table): void {
            $table->index(['status', 'updated_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('automation_run_steps', function (Blueprint $table): void {
            $table->dropIndex(['status', 'updated_at']);
        });
    }
};
