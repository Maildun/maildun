<?php

test('the unified contact profile uses the audience detail layout', function () {
    $source = file_get_contents(
        dirname(__DIR__, 2).'/resources/js/pages/contacts/show.tsx',
    );

    expect($source)->toBeString()
        ->toContain('data-test="contact-profile"')
        ->toContain('Activity')
        ->toContain('Custom attributes')
        ->toContain('Received emails')
        ->toContain('Matching segments')
        ->toContain('Consent')
        ->toContain('contact-email-row')
        ->toContain('contact-automation-row')
        ->toContain('ContactDialog')
        ->toContain('max-w-3xl')
        ->toContain('NestedPanel')
        ->toContain('MailReceive01Icon')
        ->toContain('MailOpen01Icon')
        ->toContain('MouseLeftClick01Icon')
        ->toContain('UserGroupIcon')
        ->toContain('function IconLabel')
        ->toContain('text-muted-foreground [&>svg]:size-3.5')
        ->toContain('selectedAudienceUuid')
        ->toContain('icon={Edit03Icon}');
});

test('every audience membership collapses open with its own consent actions', function () {
    $source = file_get_contents(
        dirname(__DIR__, 2).'/resources/js/pages/contacts/show.tsx',
    );

    expect($source)->toBeString()
        ->toContain('function MembershipPanel')
        ->toContain('data-test="contact-membership-row"')
        ->toContain('CollapsibleTrigger')
        ->toContain('CollapsibleContent')
        ->toContain('initialOpenMemberships')
        ->toContain('membership.can_manage')
        ->toContain('DropdownMenuTrigger')
        ->toContain('Actions for ${membership.audience.name}')
        ->toContain('View audience')
        ->toContain('onUnsubscribe')
        ->toContain('onResubscribe')
        ->not->toContain('focusedMembership');
});

test('subscriber names in lists still hover-preview and click through to the profile', function () {
    $source = file_get_contents(
        dirname(__DIR__, 2).'/resources/js/components/subscriber-hover-card.tsx',
    );

    expect($source)->toBeString()
        ->toContain('HoverCard')
        ->toContain('href')
        ->toContain('prefetch')
        ->toContain('data-test="subscriber-hover-trigger"')
        ->toContain('data-test="subscriber-hover-profile"')
        ->toContain('Profile')
        ->not->toContain('Edit03Icon');
});
