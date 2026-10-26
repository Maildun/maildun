<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Postgres cannot build an index concurrently inside a transaction.
     */
    public $withinTransaction = false;

    /**
     * Index name => column list and optional predicate.
     *
     * Postgres does not index foreign keys, so company pages, tag counts and
     * tag deletes scanned every contact or contact_tag row, and deleting a
     * subscriber scanned segment_subscriber, automation_runs and
     * automation_email_deliveries for the cascade. The predicates take no
     * bindings, which partial indexes reject.
     *
     * @var array<string, string>
     */
    private const array INDEXES = [
        'contacts_company_id_index' => 'contacts (company_id) where company_id is not null',
        'contact_tag_tag_id_index' => 'contact_tag (tag_id)',
        'segment_subscriber_subscriber_id_index' => 'segment_subscriber (subscriber_id)',
        'automation_runs_subscriber_id_index' => 'automation_runs (subscriber_id)',
        'automation_email_deliveries_subscriber_id_index' => 'automation_email_deliveries (subscriber_id) where subscriber_id is not null',
    ];

    /**
     * Run the migrations. On Postgres the indexes are built concurrently so
     * writes to these tables are not blocked while they are built.
     */
    public function up(): void
    {
        $concurrently = DB::getDriverName() === 'pgsql' ? ' concurrently' : '';

        foreach (self::INDEXES as $name => $definition) {
            DB::statement("drop index{$concurrently} if exists {$name}");
            DB::statement("create index{$concurrently} {$name} on {$definition}");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $concurrently = DB::getDriverName() === 'pgsql' ? ' concurrently' : '';

        foreach (array_keys(self::INDEXES) as $name) {
            DB::statement("drop index{$concurrently} if exists {$name}");
        }
    }
};
