<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Sent campaigns and live templates embed the public URL of each image,
     * so removing a file from the library hides the row and keeps the file.
     */
    public function up(): void
    {
        Schema::table('media', function (Blueprint $table): void {
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('media', function (Blueprint $table): void {
            $table->dropSoftDeletes();
        });
    }
};
