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
        Schema::table('audiences', function (Blueprint $table): void {
            $table->boolean('double_opt_in')->default(false)->after('last_name_mode');
            $table->foreignId('double_opt_in_email_id')
                ->nullable()
                ->after('double_opt_in')
                ->constrained('transactional_emails')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('audiences', function (Blueprint $table): void {
            $table->dropForeign(['double_opt_in_email_id']);
            $table->dropColumn(['double_opt_in', 'double_opt_in_email_id']);
        });
    }
};
