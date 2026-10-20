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
        Schema::table('transactional_emails', function (Blueprint $table): void {
            $table->foreignId('audience_id')
                ->nullable()
                ->after('team_id')
                ->constrained()
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactional_emails', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('audience_id');
        });
    }
};
