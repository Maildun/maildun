<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('team_senders', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->string('name')->nullable();
            $table->string('email');
            $table->string('reply_to')->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->timestamp('verification_sent_at')->nullable();
            $table->timestamps();

            $table->unique(['team_id', 'email']);
        });

        Schema::table('teams', function (Blueprint $table) {
            $table->foreignId('active_sender_id')
                ->nullable()
                ->index()
                ->constrained('team_senders')
                ->nullOnDelete();
        });

        DB::table('teams')
            ->whereNotNull('email_from_address')
            ->orderBy('id')
            ->each(function (object $team): void {
                $senderId = DB::table('team_senders')->insertGetId([
                    'uuid' => (string) Str::uuid(),
                    'team_id' => $team->id,
                    'name' => $team->email_from_name,
                    'email' => Str::lower(trim($team->email_from_address)),
                    'reply_to' => $team->email_reply_to,
                    // Existing senders have already been put into use. Keep
                    // those workspaces operational while new addresses always
                    // go through the verification-email flow.
                    'email_verified_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                DB::table('teams')->where('id', $team->id)->update([
                    'active_sender_id' => $senderId,
                ]);
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->dropIndex(['active_sender_id']);
            $table->dropConstrainedForeignId('active_sender_id');
        });

        Schema::dropIfExists('team_senders');
    }
};
