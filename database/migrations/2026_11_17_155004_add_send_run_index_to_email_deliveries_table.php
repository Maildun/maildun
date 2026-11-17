<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Postgres cannot build an index concurrently inside a transaction.
     */
    public $withinTransaction = false;

    /**
     * Run the migrations.
     *
     * Postgres and SQLite do not index a foreign key on their own, so paging a
     * retry's deliveries by run, and nulling the column when a run is deleted
     * with its campaign, scanned every delivery. On Postgres the index is built
     * concurrently so sends keep writing while a large table is indexed.
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('create index concurrently if not exists email_deliveries_send_run_index on email_deliveries (email_send_run_id)');

            return;
        }

        Schema::table('email_deliveries', function (Blueprint $table): void {
            $table->index('email_send_run_id', 'email_deliveries_send_run_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('drop index concurrently if exists email_deliveries_send_run_index');

            return;
        }

        Schema::table('email_deliveries', function (Blueprint $table): void {
            $table->dropIndex('email_deliveries_send_run_index');
        });
    }
};
