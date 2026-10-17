<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * soft_bounce_count is a lifetime total. Suppression needs the temporary
     * bounces since the last successful delivery, with their times, so a
     * delivery report that arrives late can still end the run correctly.
     */
    public function up(): void
    {
        Schema::table('email_address_healths', function (Blueprint $table): void {
            $table->json('recent_soft_bounces')->nullable()->after('soft_bounce_count');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('email_address_healths', function (Blueprint $table): void {
            $table->dropColumn('recent_soft_bounces');
        });
    }
};
