<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per audience per finished day, holding the member counts as
     * they stood at the end of that day. Rows are only ever inserted, so the
     * subscriber chart's past days stay put when members are later deleted
     * or resubscribed.
     */
    public function up(): void
    {
        Schema::create('audience_subscriber_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('audience_id')->constrained()->cascadeOnDelete();
            $table->date('day');
            $table->unsignedInteger('total');
            $table->unsignedInteger('unsubscribed');
            $table->unsignedInteger('pending');
            $table->unsignedInteger('new');
            $table->timestamps();

            $table->unique(['audience_id', 'day']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audience_subscriber_snapshots');
    }
};
