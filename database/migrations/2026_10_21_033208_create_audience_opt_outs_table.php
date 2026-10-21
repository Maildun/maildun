<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Opt-outs that outlive their membership row: deleting an unsubscribed
     * member or contact leaves one here, and adding the address back to the
     * audience restores it as an unsubscribed member.
     */
    public function up(): void
    {
        Schema::create('audience_opt_outs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('audience_id')->constrained()->cascadeOnDelete();
            $table->string('email');
            $table->timestamp('unsubscribed_at')->nullable();
            $table->timestamps();

            $table->unique(['audience_id', 'email']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audience_opt_outs');
    }
};
