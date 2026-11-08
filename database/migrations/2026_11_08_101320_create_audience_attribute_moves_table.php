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
        Schema::create('audience_attribute_moves', function (Blueprint $table) {
            $table->id();
            $table->foreignId('audience_id')->constrained()->cascadeOnDelete();
            // The renamed field, or null when the field was deleted.
            $table->foreignId('audience_attribute_id')->nullable()->constrained()->nullOnDelete();
            $table->string('from_key');
            $table->timestamps();

            $table->index(['audience_id', 'from_key']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audience_attribute_moves');
    }
};
