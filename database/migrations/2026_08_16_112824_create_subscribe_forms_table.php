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
        Schema::create('subscribe_forms', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('audience_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('headline');
            $table->text('description')->nullable();
            $table->string('button_label')->default('Subscribe');
            $table->string('success_message')->default('Thanks for subscribing!');
            $table->text('consent_text');
            $table->string('style')->default('card');
            $table->string('image_side')->default('right');
            $table->string('image_url', 2048)->nullable();
            $table->string('image_path')->nullable();
            $table->string('image_upload_path')->nullable();
            $table->string('logo_path')->nullable();
            $table->string('logo_shape')->default('default');
            $table->string('logo_size')->default('medium');
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['audience_id', 'created_at']);
            $table->index(['audience_id', 'published_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscribe_forms');
    }
};
