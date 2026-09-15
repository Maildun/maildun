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
            $table->string('card_padding')->default('default')->after('header_spacing');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subscribe_forms', function (Blueprint $table) {
            $table->dropColumn('card_padding');
        });
    }
};
