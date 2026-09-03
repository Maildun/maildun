<?php

use App\Actions\Transactional\RenderTransactionalContent;

test('it replaces merge tags in text and html', function () {
    $renderer = new RenderTransactionalContent;

    expect($renderer->text('Hi {{ first_name }}', ['first_name' => 'Ada']))->toBe('Hi Ada')
        ->and($renderer->text('Hi {{first_name}}', ['first_name' => 'Ada']))->toBe('Hi Ada')
        ->and($renderer->html('<p>Hi {{ first_name }}</p>', ['first_name' => 'Ada']))->toBe('<p>Hi Ada</p>');
});

test('it leaves unknown tags intact and blanks missing values', function () {
    $renderer = new RenderTransactionalContent;

    expect($renderer->text('Hi {{ first_name }} {{ last_name }}', ['last_name' => 'Lovelace']))
        ->toBe('Hi {{ first_name }} Lovelace')
        ->and($renderer->text('Hi {{ first_name }}', ['first_name' => '']))
        ->toBe('Hi ');
});

test('it html-escapes values in markup', function () {
    $renderer = new RenderTransactionalContent;

    expect($renderer->html('<p>{{ name }}</p>', ['name' => '<script>alert(1)</script>']))
        ->toBe('<p>&lt;script&gt;alert(1)&lt;/script&gt;</p>')
        ->and($renderer->text('Hi {{ name }}', ['name' => '<b>Ada</b>']))
        ->toBe('Hi <b>Ada</b>');
});

test('it detects keys in first-seen order', function () {
    $renderer = new RenderTransactionalContent;

    expect($renderer->detect(
        'Hi {{ first_name }}',
        'Preview for {{ first_name }}',
        '<p>{{ reset_url }} {{ first_name }}</p>',
    ))->toBe(['first_name', 'reset_url']);
});

test('it merges declared examples with newly detected keys', function () {
    $renderer = new RenderTransactionalContent;

    expect($renderer->merge(
        [
            ['key' => 'first_name', 'example' => 'Ada'],
            ['key' => 'unused', 'example' => 'kept'],
        ],
        ['first_name', 'reset_url'],
    ))->toBe([
        ['key' => 'first_name', 'example' => 'Ada'],
        ['key' => 'unused', 'example' => 'kept'],
        ['key' => 'reset_url', 'example' => ''],
    ]);
});
