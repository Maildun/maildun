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
            $table->boolean('trust_provider_senders')->default(false)->after('test_from_address');
        });

        Schema::table('team_senders', function (Blueprint $table) {
            $table->boolean('verified_by_provider')->default(false)->after('verified_sender_domain_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('team_senders', function (Blueprint $table) {
            $table->dropColumn('verified_by_provider');
        });

        Schema::table('team_email_integrations', function (Blueprint $table) {
            $table->dropColumn('trust_provider_senders');
        });
    }
};
