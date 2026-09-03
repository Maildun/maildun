<?php

test('subscriber list supports selecting every row', function () {
    $source = file_get_contents(dirname(__DIR__, 2).'/resources/js/pages/audiences/show.tsx');

    expect($source)->toBeString()
        ->toContain('aria-label="Select all"')
        ->toContain('data-test="subscriber-select-all"')
        ->toContain('data-test="subscriber-row-select"')
        ->toContain('data-test="subscriber-row"')
        ->toContain('SubscriberStatsChart')
        ->toContain("testId: 'subscriber-status-filter'")
        ->toContain('icon: StatusIcon')
        ->not->toContain('SubscriberStatusTabs')
        ->toContain('SubscriberHoverCard')
        ->toContain('href={showSubscriber.url([...routeArgs, subscriber.uuid])}')
        ->toContain('onEdit={canManage ? openEdit : undefined}')
        ->toContain('View profile')
        ->toContain('className="max-w-0"')
        ->not->toContain('<CardTitle>Subscribers</CardTitle>')
        ->not->toContain('<CardTitle>Segments</CardTitle>')
        ->not->toContain('<CardTitle>Subscribe forms</CardTitle>')
        ->toContain('data-test="segment-row"')
        ->toContain('data-test="subscribe-form-row"')
        ->toContain('Edit Rule')
        ->toContain('onDelete={setSegmentToDelete}')
        ->toContain('setLifecycleOpen(false)')
        ->toContain('setConsentConfirmed(false)');
});

test('subscriber stats chart uses the primary color', function () {
    $source = file_get_contents(
        dirname(__DIR__, 2).'/resources/js/components/subscriber-stats-chart.tsx',
    );

    expect($source)->toBeString()
        ->toContain("color: 'var(--primary)'")
        ->toContain("color: 'var(--muted-foreground)'")
        ->toContain("label: 'Previous'")
        ->not->toContain('Previous period')
        ->not->toContain('var(--chart-1)')
        ->not->toContain('var(--chart-2)');
});

test('subscriber stats chart active metric uses the same border as the divider', function () {
    $source = file_get_contents(
        dirname(__DIR__, 2).'/resources/js/components/subscriber-stats-chart.tsx',
    );

    expect($source)->toBeString()
        ->toContain('SlidingUnderlineList')
        ->toContain('slidingUnderlineInactiveClassName')
        ->not->toContain('after:h-0.5')
        ->not->toContain('CardHeader className="flex flex-col items-stretch border-b');
});

test('subscriber hover card previews the contact and offers profile and edit actions', function () {
    $source = file_get_contents(
        dirname(__DIR__, 2).'/resources/js/components/subscriber-hover-card.tsx',
    );

    expect($source)->toBeString()
        ->toContain('HoverCard')
        ->toContain('HoverCardTrigger')
        ->toContain('HoverCardContent')
        ->toContain('data-test="subscriber-hover-trigger"')
        ->toContain('data-test="subscriber-hover-card"')
        ->toContain('data-test="subscriber-hover-profile"')
        ->toContain('data-test="subscriber-hover-edit"')
        ->toContain('render={<Link href={href} prefetch />}')
        ->toContain('className="flex gap-2"')
        ->toContain('Profile')
        ->toContain('subscriber.avatar')
        ->not->toContain('Edit03Icon')
        ->toContain('truncate text-muted-foreground')
        ->toContain('flex max-w-full min-w-0')
        ->toContain('onEdit')
        ->toContain('href')
        ->toContain('>Status</dt>')
        ->toContain('>Source</dt>');
});

test('segment matching subscribers use the same hover card', function () {
    $source = file_get_contents(dirname(__DIR__, 2).'/resources/js/pages/segments/show.tsx');

    expect($source)->toBeString()
        ->toContain('SubscriberHoverCard')
        ->toContain('showSubscriber.url')
        ->toContain('data-test="subscriber-row"')
        ->toContain('className="max-w-0"');
});
