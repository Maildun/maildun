<?php

use App\Actions\Emails\RenderCampaignContent;
use App\Actions\Transactional\RenderTransactionalContent;

dataset('renderers', [
    'campaign' => fn (): RenderCampaignContent => new RenderCampaignContent,
    'transactional' => fn (): RenderTransactionalContent => new RenderTransactionalContent,
]);

test('a merge value cannot give a link a script scheme', function (RenderCampaignContent|RenderTransactionalContent $renderer, string $html, string $value, string $expected) {
    expect($renderer->html($html, ['website' => $value]))->toBe($expected);
})->with('renderers')->with([
    'javascript href' => ['<a href="{{ website }}">Site</a>', 'javascript:alert(1)', '<a href="#">Site</a>'],
    'single quoted' => ["<a href='{{ website }}'>Site</a>", 'JavaScript:alert(1)', "<a href='#'>Site</a>"],
    'unquoted' => ['<a href={{ website }}>Site</a>', 'javascript:alert(1)', '<a href=#>Site</a>'],
    'hidden by whitespace' => ['<a href="{{ website }}">Site</a>', " java\tscript:alert(1)", '<a href="#">Site</a>'],
    'data image source' => ['<img src="{{ website }}">', 'data:text/html;base64,PHNjcmlwdD4=', '<img src="#">'],
    'another attribute first' => ['<a title="a > b" href="{{ website }}">Site</a>', 'vbscript:run', '<a title="a > b" href="#">Site</a>'],
    'https kept' => ['<a href="{{ website }}">Site</a>', 'https://example.com/?a=1&b=2', '<a href="https://example.com/?a=1&amp;b=2">Site</a>'],
    'mailto kept' => ['<a href="{{ website }}">Mail</a>', 'mailto:ada@example.com', '<a href="mailto:ada@example.com">Mail</a>'],
    'relative kept' => ['<a href="{{ website }}">Site</a>', 'example.com/page', '<a href="example.com/page">Site</a>'],
    'author chose the scheme' => ['<a href="tel:{{ website }}">Call</a>', '+15550100', '<a href="tel:+15550100">Call</a>'],
    'inside a query string' => ['<a href="https://example.com/?ref={{ website }}">Site</a>', 'javascript:x', '<a href="https://example.com/?ref=javascript:x">Site</a>'],
    'body text untouched' => ['<p>{{ website }}</p>', 'javascript:alert(1)', '<p>javascript:alert(1)</p>'],
    'other attribute untouched' => ['<a data-href="{{ website }}" title="{{ website }}">x</a>', 'javascript:x', '<a data-href="javascript:x" title="javascript:x">x</a>'],
]);

test('plain text keeps the merge value as written', function (RenderCampaignContent|RenderTransactionalContent $renderer) {
    expect($renderer->text('<a href="{{ website }}">', ['website' => 'javascript:alert(1)']))
        ->toBe('<a href="javascript:alert(1)">');
})->with('renderers');
