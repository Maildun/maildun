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
            $table->string('language_mode')->default('hidden')->after('last_name_mode');
        });

        Schema::table('subscribers', function (Blueprint $table) {
            $table->string('language')->nullable()->after('last_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subscribers', function (Blueprint $table) {
            $table->dropColumn('language');
        });

        Schema::table('audiences', function (Blueprint $table) {
            $table->dropColumn('language_mode');
        });
    }
};
