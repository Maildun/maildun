<?php

use App\Actions\Emails\ResolveConditionalSections;
use App\Actions\Transactional\RenderTransactionalContent;

/**
 * A section as @maildun/email-builder renders it with personalization
 * markers: "-" in ids becomes "." and "-" in the JSON is written as -
 * so it can never close the comment.
 *
 * @param  array<string, mixed>  $visibleIf
 */
function personalizedSection(string $id, array $visibleIf, string $inner): string
{
    $json = str_replace('-', '-', (string) json_encode($visibleIf, JSON_UNESCAPED_SLASHES));
    $markerId = str_replace('-', '.', $id);

    return "<!--meb:if:{$markerId} {$json}-->{$inner}<!--meb:endif:{$markerId}-->";
}

/**
 * @param  list<string>  $memberships
 * @return array<string, mixed>
 */
function readerIn(array $memberships): array
{
    return [ResolveConditionalSections::DATA_KEY => $memberships];
}

test('sections show to readers in or not in the targeted groups', function (array $condition, array $memberships, bool $shown) {
    $html = 'Intro '.personalizedSection('b_1', ['conditions' => [$condition]], 'Section');

    expect((new ResolveConditionalSections)->handle($html, readerIn($memberships)))
        ->toBe($shown ? 'Intro Section' : 'Intro ');
})->with([
    'in the segment' => [['id' => 'segment:pro'], ['segment:pro', 'tag:vip'], true],
    'not in the segment' => [['id' => 'segment:pro'], ['tag:vip'], false],
    'excluded tag absent' => [['id' => 'tag:churned', 'not' => true], ['segment:pro'], true],
    'excluded tag present' => [['id' => 'tag:churned', 'not' => true], ['tag:churned'], false],
]);

test('match any shows the section when one condition passes', function () {
    $conditions = [['id' => 'segment:pro'], ['id' => 'tag:vip']];
    $resolver = new ResolveConditionalSections;

    expect($resolver->handle(personalizedSection('b_1', ['conditions' => $conditions], 'X'), readerIn(['tag:vip'])))->toBe('')
        ->and($resolver->handle(personalizedSection('b_1', ['match' => 'any', 'conditions' => $conditions], 'X'), readerIn(['tag:vip'])))->toBe('X');
});

test('nested sections resolve on their own and escaped labels stay inside the marker', function () {
    $inner = personalizedSection('vip-only', ['conditions' => [['id' => 'tag:vip', 'label' => 'a-->b']]], 'VIP');
    $html = personalizedSection('customers', ['conditions' => [['id' => 'segment:customers']]], "Customers {$inner}");
    $resolver = new ResolveConditionalSections;

    expect($resolver->handle($html, readerIn(['segment:customers', 'tag:vip'])))->toBe('Customers VIP')
        ->and($resolver->handle($html, readerIn(['segment:customers'])))->toBe('Customers ')
        ->and($resolver->handle($html, readerIn(['tag:vip'])))->toBe('');
});

test('readers without memberships and unreadable markers see no personalized content', function () {
    $resolver = new ResolveConditionalSections;

    expect($resolver->handle(personalizedSection('b_1', ['conditions' => [['id' => 'tag:vip']]], 'VIP'), ['first_name' => 'Ada']))->toBe('')
        ->and($resolver->handle('<!--meb:if:b_1 {nope}-->Secret<!--meb:endif:b_1-->', readerIn(['tag:vip'])))->toBe('');
});

test('transactional emails send personalized sections to nobody', function () {
    $html = '<p>Hi {{ name }}</p>'.personalizedSection('b_1', ['conditions' => [['id' => 'tag:vip']]], '<p>VIP</p>');

    expect((new RenderTransactionalContent)->html($html, ['name' => 'Ada']))->toBe('<p>Hi Ada</p>');
});
