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
        Schema::table('contact_imports', function (Blueprint $table) {
            $table->json('review_options')->nullable()->after('resubscribe_unsubscribed');
            $table->unsignedInteger('flagged_rows')->default(0)->after('skipped_unsubscribed');
            $table->unsignedInteger('skipped_rows')->default(0)->after('flagged_rows');
            $table->json('review_counts')->nullable()->after('skipped_rows');
            $table->json('review_flags')->nullable()->after('errors');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('contact_imports', function (Blueprint $table) {
            $table->dropColumn(['review_options', 'flagged_rows', 'skipped_rows', 'review_counts', 'review_flags']);
        });
    }
};
