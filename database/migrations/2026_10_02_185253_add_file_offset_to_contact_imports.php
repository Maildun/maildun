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
        Schema::table('contact_imports', function (Blueprint $table) {
            $table->unsignedBigInteger('file_offset')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('contact_imports', function (Blueprint $table) {
            $table->dropColumn('file_offset');
        });
    }
};
