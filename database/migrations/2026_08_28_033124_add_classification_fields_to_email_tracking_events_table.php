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
        Schema::table('email_tracking_events', function (Blueprint $table) {
            $table->string('classification', 24)->default('unknown');
            $table->string('classification_reason', 64)->nullable();
            $table->string('client_family', 64)->nullable();
            $table->string('device_type', 24)->default('unknown');

            $table->index(
                ['classification', 'occurred_at'],
                'email_tracking_events_classification_index',
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('email_tracking_events', function (Blueprint $table) {
            $table->dropIndex('email_tracking_events_classification_index');
            $table->dropColumn([
                'classification',
                'classification_reason',
                'client_family',
                'device_type',
            ]);
        });
    }
};
