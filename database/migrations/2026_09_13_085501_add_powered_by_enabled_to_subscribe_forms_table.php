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
            $table->boolean('powered_by_enabled')->default(true)->after('redirect_url');
            $table->string('powered_by_form_position')->default('bottom-center')->after('powered_by_enabled');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subscribe_forms', function (Blueprint $table) {
            $table->dropColumn([
                'powered_by_enabled',
                'powered_by_form_position',
            ]);
        });
    }
};
