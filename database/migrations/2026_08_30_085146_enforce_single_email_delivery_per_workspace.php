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
        Schema::table('team_email_integrations', function (Blueprint $table) {
            $table->unsignedInteger('verification_version')->default(1)->after('last_tested_at');
            $table->string('test_from_address')->nullable()->after('verification_version');
        });

        Schema::table('team_senders', function (Blueprint $table) {
            $table->unsignedBigInteger('verified_email_integration_id')->nullable()->after('verification_sent_at');
            $table->unsignedInteger('verified_email_integration_version')->nullable()->after('verified_email_integration_id');
        });

        DB::table('teams')
            ->select(['id', 'active_email_integration_id', 'active_sender_id'])
            ->orderBy('id')
            ->each(function (object $team): void {
                $connections = DB::table('team_email_integrations')
                    ->where('team_id', $team->id)
                    ->orderByDesc('last_tested_at')
                    ->orderByDesc('updated_at')
                    ->orderByDesc('id')
                    ->get(['id', 'authorized_from_address', 'sender_authorized_at']);

                if ($connections->isEmpty()) {
                    return;
                }

                $connection = $connections->firstWhere('id', $team->active_email_integration_id)
                    ?? $connections->first();

                DB::table('team_email_integrations')
                    ->where('team_id', $team->id)
                    ->where('id', '!=', $connection->id)
                    ->delete();

                DB::table('team_email_integrations')
                    ->where('id', $connection->id)
                    ->update(['test_from_address' => $connection->authorized_from_address]);

                if ($team->active_sender_id === null
                    || $connection->sender_authorized_at === null
                    || $connection->authorized_from_address === null) {
                    return;
                }

                DB::table('team_senders')
                    ->where('id', $team->active_sender_id)
                    ->whereRaw('LOWER(email) = LOWER(?)', [$connection->authorized_from_address])
                    ->update([
                        'verified_email_integration_id' => $connection->id,
                        'verified_email_integration_version' => 1,
                    ]);
            });

        Schema::table('teams', function (Blueprint $table) {
            $table->dropIndex(['active_email_integration_id']);
            $table->dropConstrainedForeignId('active_email_integration_id');
            $table->dropColumn('requires_email_integration');
        });

        Schema::table('team_email_integrations', function (Blueprint $table) {
            $table->dropIndex(['team_id', 'provider']);
            $table->dropColumn(['authorized_from_address', 'sender_authorized_at']);
            $table->unique('team_id');
        });

        Schema::table('team_senders', function (Blueprint $table) {
            $table->foreign('verified_email_integration_id')
                ->references('id')
                ->on('team_email_integrations')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->boolean('requires_email_integration')->default(false);
            $table->foreignId('active_email_integration_id')
                ->nullable()
                ->index()
                ->constrained('team_email_integrations')
                ->nullOnDelete();
        });

        DB::table('team_email_integrations')
            ->orderBy('id')
            ->each(function (object $connection): void {
                DB::table('teams')
                    ->where('id', $connection->team_id)
                    ->update([
                        'requires_email_integration' => true,
                        'active_email_integration_id' => $connection->last_tested_at === null
                            ? null
                            : $connection->id,
                    ]);
            });

        Schema::table('team_senders', function (Blueprint $table) {
            $table->dropForeign(['verified_email_integration_id']);
            $table->dropColumn(['verified_email_integration_id', 'verified_email_integration_version']);
        });

        Schema::table('team_email_integrations', function (Blueprint $table) {
            $table->dropUnique(['team_id']);
            $table->string('authorized_from_address')->nullable();
            $table->timestamp('sender_authorized_at')->nullable();
            $table->index(['team_id', 'provider']);
            $table->dropColumn(['verification_version', 'test_from_address']);
        });
    }
};
