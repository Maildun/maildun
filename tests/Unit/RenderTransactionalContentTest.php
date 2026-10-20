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

test('it restores markdown merge tags in links and text', function () {
    $renderer = new RenderTransactionalContent;
    $data = ['unsubscribe_url' => 'https://maildun.test/u'];

    expect($renderer->html('<a href="%7B%7B%20unsubscribe_url%20%7D%7D">Leave</a>', $data))
        ->toBe('<a href="https://maildun.test/u">Leave</a>')
        ->and($renderer->html('<p>[Leave]({{ unsubscribe_url }}) or [Go](%7B%7Bunsubscribe_url%7D%7D)</p>', $data))
        ->toBe('<p><a href="https://maildun.test/u">Leave</a> or <a href="https://maildun.test/u">Go</a></p>');
});

test('it leaves markdown merge syntax alone in other attributes and code', function () {
    $renderer = new RenderTransactionalContent;
    $data = ['unsubscribe_url' => 'https://maildun.test/u'];

    expect($renderer->html('<p>Tags look like %7B%7Bunsubscribe_url%7D%7D when encoded.</p>', $data))
        ->toBe('<p>Tags look like %7B%7Bunsubscribe_url%7D%7D when encoded.</p>')
        ->and($renderer->html('<img alt="[Leave]({{ unsubscribe_url }})" title="%7B%7Bunsubscribe_url%7D%7D">', $data))
        ->toBe('<img alt="[Leave](https://maildun.test/u)" title="%7B%7Bunsubscribe_url%7D%7D">')
        ->and($renderer->html('<pre><code>[Leave](%7B%7Bunsubscribe_url%7D%7D)</code></pre>', $data))
        ->toBe('<pre><code>[Leave](%7B%7Bunsubscribe_url%7D%7D)</code></pre>');
});
