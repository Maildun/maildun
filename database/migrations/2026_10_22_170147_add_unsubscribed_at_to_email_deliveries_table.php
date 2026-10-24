<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * When the recipient opted out through this delivery's unsubscribe link,
     * so the campaign report can count unsubscribes per campaign.
     */
    public function up(): void
    {
        Schema::table('email_deliveries', function (Blueprint $table) {
            $table->timestamp('unsubscribed_at')->nullable()->after('complained_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('email_deliveries', function (Blueprint $table) {
            $table->dropColumn('unsubscribed_at');
        });
    }
};
