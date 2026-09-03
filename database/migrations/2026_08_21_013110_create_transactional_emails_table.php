<?php

use App\Enums\EmailEditor;
use App\Enums\TransactionalEmailStatus;
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
        Schema::create('transactional_emails', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->string('description')->nullable();
            $table->string('subject');
            $table->string('preheader')->nullable();
            $table->string('from_name')->nullable();
            $table->string('from_address')->nullable();
            $table->string('reply_to')->nullable();
            $table->string('editor')->default(EmailEditor::Html->value);
            $table->string('status')->default(TransactionalEmailStatus::Draft->value);
            $table->longText('html')->nullable();
            $table->json('design')->nullable();
            $table->json('variables')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('last_tested_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['team_id', 'slug']);
            $table->index(['team_id', 'updated_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactional_emails');
    }
};
