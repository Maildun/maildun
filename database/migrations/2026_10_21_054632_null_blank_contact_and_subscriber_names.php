<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Contacts added in the app without a name were saved with '' instead of
     * null, and their audience memberships copied it. A CSV import's "fill
     * empty fields" only fills null names, so it skipped these people.
     */
    public function up(): void
    {
        foreach (['contacts', 'subscribers'] as $table) {
            foreach (['first_name', 'last_name'] as $column) {
                DB::table($table)
                    ->whereNotNull($column)
                    ->whereRaw("TRIM({$column}) = ''")
                    ->update([$column => null]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Null and '' meant the same thing to people; there is nothing to restore.
    }
};
