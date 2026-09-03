<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('email_tracking_aggregates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('email_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('total_opens_count')->default(0);
            $table->unsignedBigInteger('unique_opens_count')->default(0);
            $table->unsignedBigInteger('total_clicks_count')->default(0);
            $table->unsignedBigInteger('unique_clicks_count')->default(0);
            $table->unsignedBigInteger('revision')->default(0);
            $table->timestamp('first_opened_at')->nullable();
            $table->timestamp('last_opened_at')->nullable();
            $table->timestamp('first_clicked_at')->nullable();
            $table->timestamp('last_clicked_at')->nullable();
            $table->timestamps();
        });

        Schema::create('email_link_tracking_aggregates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('email_link_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('total_clicks_count')->default(0);
            $table->unsignedBigInteger('unique_clicks_count')->default(0);
            $table->unsignedBigInteger('revision')->default(0);
            $table->timestamp('first_clicked_at')->nullable();
            $table->timestamp('last_clicked_at')->nullable();
            $table->timestamps();
        });

        Schema::table('email_deliveries', function (Blueprint $table) {
            $table->index(['email_id', 'opens_count'], 'email_deliveries_open_filter_index');
            $table->index(['email_id', 'clicks_count'], 'email_deliveries_click_filter_index');
            $table->index(['email_id', 'sent_at'], 'email_deliveries_sent_timeline_index');
            $table->index(['email_id', 'delivered_at'], 'email_deliveries_delivered_timeline_index');
            $table->index(['email_id', 'first_opened_at'], 'email_deliveries_open_timeline_index');
            $table->index(['email_id', 'first_clicked_at'], 'email_deliveries_click_timeline_index');
            $table->index(['email_id', 'bounced_at'], 'email_deliveries_bounce_timeline_index');
            $table->index(
                ['email_id', 'provider', 'uses_team_email_integration'],
                'email_deliveries_transport_index',
            );
            $table->index(['subscriber_id', 'sent_at'], 'email_deliveries_subscriber_sent_index');
        });

        Schema::table('email_link_clicks', function (Blueprint $table) {
            $table->index(['email_link_id', 'email_delivery_id'], 'email_link_clicks_link_delivery_index');
        });

        $this->backfillEmailAggregates();
        $this->backfillLinkAggregates();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('email_link_clicks', function (Blueprint $table) {
            $table->dropIndex('email_link_clicks_link_delivery_index');
        });

        Schema::table('email_deliveries', function (Blueprint $table) {
            $table->dropIndex('email_deliveries_open_filter_index');
            $table->dropIndex('email_deliveries_click_filter_index');
            $table->dropIndex('email_deliveries_sent_timeline_index');
            $table->dropIndex('email_deliveries_delivered_timeline_index');
            $table->dropIndex('email_deliveries_open_timeline_index');
            $table->dropIndex('email_deliveries_click_timeline_index');
            $table->dropIndex('email_deliveries_bounce_timeline_index');
            $table->dropIndex('email_deliveries_subscriber_sent_index');
        });

        DB::statement('DROP INDEX IF EXISTS email_deliveries_transport_index');

        Schema::dropIfExists('email_link_tracking_aggregates');
        Schema::dropIfExists('email_tracking_aggregates');
    }

    private function backfillEmailAggregates(): void
    {
        DB::table('email_tracking_aggregates')->insertUsing(
            [
                'email_id',
                'total_opens_count',
                'unique_opens_count',
                'total_clicks_count',
                'unique_clicks_count',
                'first_opened_at',
                'last_opened_at',
                'first_clicked_at',
                'last_clicked_at',
                'created_at',
                'updated_at',
            ],
            DB::table('emails')
                ->leftJoin('email_deliveries', 'email_deliveries.email_id', '=', 'emails.id')
                ->selectRaw('emails.id')
                ->selectRaw('COALESCE(SUM(email_deliveries.opens_count), 0)')
                ->selectRaw('COALESCE(SUM(CASE WHEN email_deliveries.opens_count > 0 THEN 1 ELSE 0 END), 0)')
                ->selectRaw('COALESCE(SUM(email_deliveries.clicks_count), 0)')
                ->selectRaw('COALESCE(SUM(CASE WHEN email_deliveries.clicks_count > 0 THEN 1 ELSE 0 END), 0)')
                ->selectRaw('MIN(email_deliveries.first_opened_at)')
                ->selectRaw('MAX(email_deliveries.last_opened_at)')
                ->selectRaw('MIN(email_deliveries.first_clicked_at)')
                ->selectRaw('MAX(email_deliveries.last_clicked_at)')
                ->selectRaw('CURRENT_TIMESTAMP')
                ->selectRaw('CURRENT_TIMESTAMP')
                ->groupBy('emails.id'),
        );
    }

    private function backfillLinkAggregates(): void
    {
        DB::table('email_link_tracking_aggregates')->insertUsing(
            [
                'email_link_id',
                'total_clicks_count',
                'unique_clicks_count',
                'first_clicked_at',
                'last_clicked_at',
                'created_at',
                'updated_at',
            ],
            DB::table('email_links')
                ->leftJoin('email_link_clicks', 'email_link_clicks.email_link_id', '=', 'email_links.id')
                ->selectRaw('email_links.id')
                ->selectRaw('COALESCE(SUM(email_link_clicks.clicks_count), 0)')
                ->selectRaw('COUNT(email_link_clicks.id)')
                ->selectRaw('MIN(email_link_clicks.first_clicked_at)')
                ->selectRaw('MAX(email_link_clicks.last_clicked_at)')
                ->selectRaw('CURRENT_TIMESTAMP')
                ->selectRaw('CURRENT_TIMESTAMP')
                ->groupBy('email_links.id'),
        );
    }
};
