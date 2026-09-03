<?php

use App\Enums\EmailEditor;
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
        Schema::create('email_templates', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            // Starter templates ship with the app and belong to no team.
            $table->foreignId('team_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('description')->nullable();
            $table->string('subject')->nullable();
            $table->string('preheader')->nullable();
            $table->string('editor')->default(EmailEditor::Html->value);
            $table->longText('html')->nullable();
            $table->json('design')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->index(['team_id', 'position']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('email_templates');
    }
};
