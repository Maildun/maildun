<?php

use Illuminate\Support\Facades\Schema;

test('company, tag and subscriber lookups are indexed', function (string $table, string $index, array $columns) {
    $definition = collect(Schema::getIndexes($table))->firstWhere('name', $index);

    expect($definition)->not->toBeNull()
        ->and($definition['columns'])->toBe($columns);
})->with([
    'contacts by company' => ['contacts', 'contacts_company_id_index', ['company_id']],
    'contact tags by tag' => ['contact_tag', 'contact_tag_tag_id_index', ['tag_id']],
    'segment members by subscriber' => ['segment_subscriber', 'segment_subscriber_subscriber_id_index', ['subscriber_id']],
    'automation runs by subscriber' => ['automation_runs', 'automation_runs_subscriber_id_index', ['subscriber_id']],
    'automation emails by subscriber' => ['automation_email_deliveries', 'automation_email_deliveries_subscriber_id_index', ['subscriber_id']],
]);
