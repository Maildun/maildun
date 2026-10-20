<?php

use Illuminate\Support\Facades\Schema;

test('the SES feedback lookups are indexed', function () {
    expect(Schema::hasIndex('email_delivery_attempts', 'email_attempts_integration_sent_index'))->toBeTrue()
        ->and(Schema::hasIndex('email_provider_events', 'email_events_topic_created_index'))->toBeTrue();
});
