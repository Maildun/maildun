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
        Schema::table('emails', function (Blueprint $table) {
            $table->longText('plain_text')->nullable()->after('html');
            $table->string('query_string', 2048)->nullable()->after('plain_text');
            $table->boolean('track_clicks')->default(true)->after('query_string');
            $table->boolean('track_opens')->default(true)->after('track_clicks');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('emails', function (Blueprint $table) {
            $table->dropColumn([
                'plain_text',
                'query_string',
                'track_clicks',
                'track_opens',
            ]);
        });
    }
};
