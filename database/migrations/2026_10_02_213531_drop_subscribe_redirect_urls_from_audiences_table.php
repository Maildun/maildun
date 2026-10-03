<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Subscribe forms own their post-subscribe redirect, so the audience-level
     * subscribe and already-subscribed URLs were never read.
     */
    public function up(): void
    {
        Schema::table('audiences', function (Blueprint $table) {
            $table->dropColumn(['subscribed_url', 'already_subscribed_url']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('audiences', function (Blueprint $table) {
            $table->string('subscribed_url', 2048)->nullable()->after('notification_email');
            $table->string('already_subscribed_url', 2048)->nullable()->after('subscribed_url');
        });
    }
};
