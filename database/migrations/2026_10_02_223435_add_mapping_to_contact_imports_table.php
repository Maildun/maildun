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
        Schema::table('contact_imports', function (Blueprint $table) {
            $table->string('delimiter', 1)->default(',')->after('path');
            $table->json('headers')->nullable()->after('delimiter');
            $table->json('sample_rows')->nullable()->after('headers');
            $table->json('column_map')->nullable()->after('sample_rows');
            $table->string('merge_strategy')->default('skip')->after('column_map');
            $table->json('tag_names')->nullable()->after('merge_strategy');
            $table->boolean('resubscribe_unsubscribed')->default(false)->after('tag_names');
            $table->timestamp('consent_confirmed_at')->nullable()->after('resubscribe_unsubscribed');
            $table->unsignedInteger('last_row_number')->default(0)->after('file_offset');
            $table->unsignedInteger('updated_contacts')->default(0)->after('imported_contacts');
            $table->unsignedInteger('skipped_unsubscribed')->default(0)->after('duplicate_rows');
            $table->text('failure_message')->nullable()->after('errors');
            $table->timestamp('started_at')->nullable()->after('failure_message');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('contact_imports', function (Blueprint $table) {
            $table->dropColumn([
                'delimiter',
                'headers',
                'sample_rows',
                'column_map',
                'merge_strategy',
                'tag_names',
                'resubscribe_unsubscribed',
                'consent_confirmed_at',
                'last_row_number',
                'updated_contacts',
                'skipped_unsubscribed',
                'failure_message',
                'started_at',
            ]);
        });
    }
};
