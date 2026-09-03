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
        Schema::create('email_link_clicks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('email_delivery_id')->constrained()->cascadeOnDelete();
            $table->foreignId('email_link_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('clicks_count')->default(1);
            $table->timestamp('first_clicked_at');
            $table->timestamp('last_clicked_at');
            $table->timestamps();

            $table->unique(['email_delivery_id', 'email_link_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('email_link_clicks');
    }
};
