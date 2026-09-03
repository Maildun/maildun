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
        Schema::table('email_provider_events', function (Blueprint $table) {
            $table->string('ses_sns_topic_arn_hash', 64)->nullable()->after('provider')->index();
            $table->foreignId('email_delivery_attempt_id')
                ->nullable()
                ->after('email_delivery_id')
                ->index()
                ->constrained()
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('email_provider_events', function (Blueprint $table) {
            $table->dropForeign(['email_delivery_attempt_id']);
            $table->dropIndex(['email_delivery_attempt_id']);
            $table->dropIndex(['ses_sns_topic_arn_hash']);
            $table->dropColumn(['email_delivery_attempt_id', 'ses_sns_topic_arn_hash']);
        });
    }
};
