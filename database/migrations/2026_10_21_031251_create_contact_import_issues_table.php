<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Every failed and flagged row of an import, so the downloadable report is
     * complete. The errors and review_flags JSON columns keep a short preview.
     */
    public function up(): void
    {
        Schema::create('contact_import_issues', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contact_import_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('row_number')->nullable();
            $table->string('email')->nullable();
            $table->string('outcome', 20);
            $table->string('issue', 40)->nullable();
            $table->text('detail')->nullable();

            $table->index(['contact_import_id', 'id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contact_import_issues');
    }
};
