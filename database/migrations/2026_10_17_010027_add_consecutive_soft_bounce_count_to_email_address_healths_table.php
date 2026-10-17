<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * soft_bounce_count is a lifetime total. Suppression needs the run of
     * temporary bounces since the last successful delivery.
     */
    public function up(): void
    {
        Schema::table('email_address_healths', function (Blueprint $table): void {
            $table->unsignedInteger('consecutive_soft_bounce_count')->default(0)->after('soft_bounce_count');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('email_address_healths', function (Blueprint $table): void {
            $table->dropColumn('consecutive_soft_bounce_count');
        });
    }
};
