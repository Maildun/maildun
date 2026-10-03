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
        Schema::create('contact_import_emails', function (Blueprint $table) {
            $table->foreignId('contact_import_id')->constrained()->cascadeOnDelete();
            $table->string('email');
            $table->unsignedInteger('row_number');

            $table->primary(['contact_import_id', 'email']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contact_import_emails');
    }
};
