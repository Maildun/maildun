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
        Schema::create('email_tracking_insight_aggregates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('email_id')->constrained()->cascadeOnDelete();
            $table->string('classification', 24);
            $table->string('dimension', 32);
            $table->string('dimension_key', 191);
            $table->string('dimension_label');
            $table->unsignedBigInteger('total_opens_count')->default(0);
            $table->unsignedBigInteger('unique_opens_count')->default(0);
            $table->unsignedBigInteger('total_clicks_count')->default(0);
            $table->unsignedBigInteger('unique_clicks_count')->default(0);
            $table->timestamp('first_opened_at')->nullable();
            $table->timestamp('last_opened_at')->nullable();
            $table->timestamp('first_clicked_at')->nullable();
            $table->timestamp('last_clicked_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['email_id', 'classification', 'dimension', 'dimension_key'],
                'email_tracking_insights_dimension_unique',
            );
            $table->index(
                ['email_id', 'classification', 'dimension', 'unique_opens_count'],
                'email_tracking_insights_open_report_index',
            );
            $table->index(
                ['email_id', 'classification', 'dimension', 'unique_clicks_count'],
                'email_tracking_insights_click_report_index',
            );
        });

        Schema::create('email_tracking_insight_uniques', function (Blueprint $table) {
            $table->id();
            $table->foreignId('email_tracking_insight_aggregate_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->foreignId('email_delivery_id')->constrained()->cascadeOnDelete();
            $table->timestamp('first_opened_at')->nullable();
            $table->timestamp('first_clicked_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['email_tracking_insight_aggregate_id', 'email_delivery_id'],
                'email_tracking_insight_uniques_delivery_unique',
            );
            $table->index(
                ['email_delivery_id', 'email_tracking_insight_aggregate_id'],
                'email_tracking_insight_uniques_delivery_index',
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('email_tracking_insight_uniques');
        Schema::dropIfExists('email_tracking_insight_aggregates');
    }
};
