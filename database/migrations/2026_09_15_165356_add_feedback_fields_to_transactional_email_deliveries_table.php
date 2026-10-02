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
        Schema::table('transactional_email_deliveries', function (Blueprint $table) {
            $table->string('ses_configuration_set')->nullable()->after('provider_message_id');
            $table->char('ses_sns_topic_arn_hash', 64)->nullable()->after('ses_configuration_set');
            $table->timestamp('delivered_at')->nullable()->after('sent_at');
            $table->timestamp('delayed_at')->nullable()->after('delivered_at');
            $table->timestamp('bounced_at')->nullable()->after('delayed_at');
            $table->timestamp('complained_at')->nullable()->after('bounced_at');

            $table->index(
                ['provider', 'ses_sns_topic_arn_hash'],
                'transactional_delivery_provider_topic_index',
            );
            $table->index(
                ['provider', 'provider_message_id', 'ses_sns_topic_arn_hash'],
                'transactional_delivery_message_topic_index',
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactional_email_deliveries', function (Blueprint $table) {
            $table->dropIndex('transactional_delivery_provider_topic_index');
            $table->dropIndex('transactional_delivery_message_topic_index');
            $table->dropColumn([
                'ses_configuration_set',
                'ses_sns_topic_arn_hash',
                'delivered_at',
                'delayed_at',
                'bounced_at',
                'complained_at',
            ]);
        });
    }
};
