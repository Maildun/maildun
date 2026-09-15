<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('subscribe_forms', function (Blueprint $table): void {
            $table->string('logo_position')->default('center');
            $table->string('header_spacing')->default('default');
        });

        DB::table('subscribe_forms')
            ->where('style', 'split')
            ->update(['logo_position' => 'left']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subscribe_forms', function (Blueprint $table): void {
            $table->dropColumn(['logo_position', 'header_spacing']);
        });
    }
};
