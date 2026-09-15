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
            $table->foreignId('transactional_email_delivery_id')
                ->nullable()
                ->after('email_delivery_attempt_id')
                ->constrained()
                ->nullOnDelete();
            $table->foreignId('automation_email_delivery_id')
                ->nullable()
                ->after('transactional_email_delivery_id')
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
            $table->dropConstrainedForeignId('automation_email_delivery_id');
            $table->dropConstrainedForeignId('transactional_email_delivery_id');
        });
    }
};
