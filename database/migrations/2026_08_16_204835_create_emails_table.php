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
        Schema::create('emails', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('audience_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('segment_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('email_template_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('subject');
            $table->string('preheader')->nullable();
            $table->string('from_name')->nullable();
            $table->string('from_address')->nullable();
            $table->string('reply_to')->nullable();
            $table->string('editor')->default(EmailEditor::Html->value);
            $table->longText('html')->nullable();
            $table->json('design')->nullable();
            $table->timestamp('last_tested_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->string('status')->default('draft')->index();
            $table->uuid('batch_id')->nullable()->index();
            $table->unsignedInteger('recipient_count')->default(0);
            $table->timestamp('send_started_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['team_id', 'updated_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('emails');
    }
};
