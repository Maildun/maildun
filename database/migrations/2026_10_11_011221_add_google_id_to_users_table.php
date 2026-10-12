<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('google_id')->nullable()->unique()->after('email');
        });

        // Accounts created through "Continue with Google" have no password
        // until the person sets one.
        Schema::table('users', function (Blueprint $table): void {
            $table->string('password')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropUnique(['google_id']);
            $table->dropColumn('google_id');
        });

        // Password stays nullable while any Google-only account exists:
        // restoring NOT NULL would fail, and inventing a password would hand
        // those accounts a credential nobody chose.
        if (DB::table('users')->whereNull('password')->doesntExist()) {
            Schema::table('users', function (Blueprint $table): void {
                $table->string('password')->nullable(false)->change();
            });
        }
    }
};
