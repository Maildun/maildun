<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * A series without a conversion event key stays an engagement-only report.
     */
    public function up(): void
    {
        Schema::table('campaign_series', function (Blueprint $table) {
            $table->string('conversion_event_key', 64)->nullable()->after('primary_cta_url');
            $table->unsignedSmallInteger('attribution_window_days')->default(30)->after('conversion_event_key');
            $table->unsignedInteger('conversion_target')->nullable()->after('attribution_window_days');
            $table->char('value_currency', 3)->nullable()->after('conversion_target');

            $table->index(['team_id', 'conversion_event_key']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('campaign_series', function (Blueprint $table) {
            $table->dropIndex(['team_id', 'conversion_event_key']);
            $table->dropColumn([
                'conversion_event_key',
                'attribution_window_days',
                'conversion_target',
                'value_currency',
            ]);
        });
    }
};
