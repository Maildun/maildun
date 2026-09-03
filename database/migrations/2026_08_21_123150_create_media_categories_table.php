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
        Schema::create('media_categories', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->timestamps();

            $table->unique(['team_id', 'name']);
        });

        Schema::table('media', function (Blueprint $table) {
            $table->foreignId('media_category_id')->nullable()->constrained()->nullOnDelete();
            $table->index(['team_id', 'media_category_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('media', function (Blueprint $table) {
            $table->dropIndex(['team_id', 'media_category_id']);
            $table->dropConstrainedForeignId('media_category_id');
        });

        Schema::dropIfExists('media_categories');
    }
};
