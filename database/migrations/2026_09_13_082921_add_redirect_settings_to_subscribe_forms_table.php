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
        Schema::table('subscribe_forms', function (Blueprint $table) {
            $table->boolean('redirect_enabled')->default(false)->after('success_message');
            $table->string('redirect_url', 2048)->nullable()->after('redirect_enabled');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subscribe_forms', function (Blueprint $table) {
            $table->dropColumn(['redirect_enabled', 'redirect_url']);
        });
    }
};
