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
        Schema::table('subscribe_forms', function (Blueprint $table): void {
            $table->string('text_alignment')->default('center');
            $table->string('success_heading')->default('You’re subscribed!');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subscribe_forms', function (Blueprint $table): void {
            $table->dropColumn(['text_alignment', 'success_heading']);
        });
    }
};
