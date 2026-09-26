<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A draft can be set to send at a later time. It stays an editable draft
     * until emails:send-scheduled starts it; if starting fails then, the
     * reason is kept so the author can see why it did not go out.
     */
    public function up(): void
    {
        Schema::table('emails', function (Blueprint $table) {
            $table->timestamp('scheduled_at')->nullable()->after('send_started_at');
            $table->text('schedule_error')->nullable()->after('scheduled_at');

            $table->index(['status', 'scheduled_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('emails', function (Blueprint $table) {
            $table->dropIndex(['status', 'scheduled_at']);
            $table->dropColumn(['scheduled_at', 'schedule_error']);
        });
    }
};
