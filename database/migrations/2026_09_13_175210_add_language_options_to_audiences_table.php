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
        Schema::table('audiences', function (Blueprint $table) {
            $table->json('allowed_languages')->nullable()->after('language_mode');
            $table->string('default_language')->nullable()->after('allowed_languages');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('audiences', function (Blueprint $table) {
            $table->dropColumn(['allowed_languages', 'default_language']);
        });
    }
};
