<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('team_email_integrations', function (Blueprint $table) {
            $table->timestamp('test_requested_at')->nullable();
            $table->timestamp('test_failed_at')->nullable();
            $table->string('test_failure', 500)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('team_email_integrations', function (Blueprint $table) {
            $table->dropColumn(['test_requested_at', 'test_failed_at', 'test_failure']);
        });
    }
};
