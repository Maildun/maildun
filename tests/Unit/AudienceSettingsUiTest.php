<?php

test('audience settings keep save inside the card with small overview icons and placeholders', function () {
    $pages = dirname(__DIR__, 2).'/resources/js/pages';

    $edit = file_get_contents($pages.'/audiences/edit.tsx');
    $sender = file_get_contents($pages.'/audiences/settings/sender.tsx');
    $notifications = file_get_contents($pages.'/audiences/settings/notifications.tsx');
    $doubleOptIn = file_get_contents($pages.'/audiences/settings/double-opt-in.tsx');
    $landingPages = file_get_contents($pages.'/audiences/settings/landing-pages.tsx');
    $attributes = file_get_contents($pages.'/audiences/settings/attributes.tsx');
    $danger = file_get_contents($pages.'/audiences/settings/danger.tsx');

    expect($edit)->toBeString()
        ->toContain('<HugeiconsIcon icon={icon} className="size-4" />')
        ->toContain('placeholder="Product newsletter"')
        ->toContain('placeholder="What this audience is for"')
        ->toContain('variant="inset"')
        ->toContain('flex justify-end border-t px-6 py-5 sm:px-7')
        ->toContain('data-test="save-audience-settings"')
        ->not->toContain('<div className="flex justify-end">');

    expect($sender)->toBeString()
        ->toContain('variant="inset"')
        ->toContain('flex justify-end border-t px-6 py-5 sm:px-7')
        ->toContain('data-test="save-audience-settings"')
        ->toContain('data-test="audience-sender-select"')
        ->toContain('name="sender_uuid"')
        ->toContain('SelectGroup')
        ->toContain('Manage senders')
        ->not->toContain('audience-from-name')
        ->not->toContain('audience-from-address')
        ->not->toContain('audience-reply-to')
        ->not->toContain('<div className="flex justify-end">');

    expect($notifications)->toBeString()
        ->toContain('variant="inset"')
        ->toContain('flex justify-end border-t px-6 py-5 sm:px-7')
        ->toContain('placeholder="you@example.com"')
        ->not->toContain('<div className="flex justify-end">');

    expect($doubleOptIn)->toBeString()
        ->toContain('Require email confirmation')
        ->toContain('data-test="audience-double-opt-in"')
        ->toContain('data-test="audience-double-opt-in-email"')
        ->toContain('SelectGroup')
        ->toContain('transactionalEmailsIndex')
        ->toContain('flex justify-end border-t px-6 py-5 sm:px-7');

    expect($landingPages)->toBeString()
        ->toContain('variant="inset"')
        ->toContain('flex justify-end border-t px-6 py-5 sm:px-7')
        ->toContain('placeholder="https://example.com/thanks"')
        ->toContain('placeholder="https://example.com/already-subscribed"')
        ->toContain('placeholder="https://example.com/unsubscribed"')
        ->not->toContain('<div className="flex justify-end">');

    expect($attributes)->toBeString()
        ->toContain('variant="inset"')
        ->toContain('placeholder="Company"')
        ->toContain('placeholder="company"')
        ->toContain('data-test="attribute-required-checkbox"')
        ->toContain('<TableHead>Required</TableHead>')
        ->toContain('title="Subscriber fields"')
        ->toContain('Email')
        ->toContain('data-test="save-subscriber-fields"');

    expect($danger)->toBeString()
        ->toContain('<DeleteAudienceDialog');
});
