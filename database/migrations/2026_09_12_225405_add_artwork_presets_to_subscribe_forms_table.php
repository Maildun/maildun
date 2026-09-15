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
            $table->string('artwork_type')->default('upload')->after('image_side');
            $table->string('artwork_preset')->nullable()->after('artwork_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subscribe_forms', function (Blueprint $table): void {
            $table->dropColumn(['artwork_type', 'artwork_preset']);
        });
    }
};
