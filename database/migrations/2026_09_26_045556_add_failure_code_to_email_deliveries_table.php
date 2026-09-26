<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A grouping code next to failure_reason so the report can count the top
     * causes of failed, rejected and delayed deliveries.
     */
    public function up(): void
    {
        Schema::table('email_deliveries', function (Blueprint $table) {
            $table->string('failure_code')->nullable()->after('failure_reason');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('email_deliveries', function (Blueprint $table) {
            $table->dropColumn('failure_code');
        });
    }
};
