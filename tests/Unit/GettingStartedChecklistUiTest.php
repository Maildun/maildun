<?php

test('the whole getting started summary toggles the checklist', function () {
    $source = file_get_contents(
        dirname(__DIR__, 2).'/resources/js/components/getting-started-checklist.tsx',
    );

    expect($source)->toBeString()
        ->toContain('<CollapsibleTrigger')
        ->toContain('<Button')
        ->toContain('variant="ghost"')
        ->toContain('className="h-auto w-full items-start justify-start')
        ->toContain("? 'Show getting started steps'")
        ->toContain(": 'Hide getting started steps'")
        ->not->toContain('size="icon-xs"');
});

test('the getting started checklist animates its panel and steps when opened', function () {
    $source = file_get_contents(
        dirname(__DIR__, 2).'/resources/js/components/getting-started-checklist.tsx',
    );

    expect($source)->toBeString()
        ->toContain('h-(--collapsible-panel-height)')
        ->toContain('data-starting-style:h-0')
        ->toContain('data-ending-style:h-0')
        ->toContain('motion-reduce:transition-none')
        ->toContain('motion-safe:animate-in')
        ->toContain('motion-safe:fade-in-0')
        ->toContain('motion-safe:slide-in-from-bottom-1')
        ->toContain('style={{ animationDelay: `${index * 45}ms` }}');
});

test('the getting started step preview uses a themed card over a grainy gradient', function () {
    $source = file_get_contents(
        dirname(__DIR__, 2).'/resources/js/components/getting-started-checklist.tsx',
    );

    expect($source)->toBeString()
        ->toContain('className="w-72 overflow-hidden rounded-xl p-2"')
        ->not->toContain('bg-foreground p-2 text-background')
        ->not->toContain('dark:bg-card dark:text-card-foreground')
        ->not->toContain('text-background/70')
        ->toContain('dithered relative flex aspect-video')
        ->toContain('soft-wash')
        ->not->toContain('from-primary/30 via-primary/5 to-transparent');
});

test('the getting started step preview renders a large static sidebar icon', function () {
    $source = file_get_contents(
        dirname(__DIR__, 2).'/resources/js/components/getting-started-checklist.tsx',
    );

    expect($source)->toBeString()
        ->toContain('icon={copy.icon}')
        ->toContain('className="size-14 text-white drop-shadow-sm"')
        ->not->toContain('image?: string;')
        ->not->toContain('src={copy.image}')
        // The card art holds still; only the wash behind it moves. These are the
        // icon-only animation classes, so their absence is what pins that down —
        // the checklist rows keep their own stagger.
        ->not->toContain('motion-safe:zoom-in-75')
        ->not->toContain('motion-safe:slide-in-from-bottom-6')
        ->not->toContain("animationDelay: '80ms'");
});

test('the checklist step icons match the sidebar nav icons', function () {
    $root = dirname(__DIR__, 2).'/resources/js/components/';
    $sidebar = file_get_contents($root.'app-sidebar.tsx');
    $checklist = file_get_contents($root.'getting-started-checklist.tsx');

    // Each step that has a sidebar counterpart reuses that row's icon.
    $shared = [
        'UserGroupIcon',        // Audiences
        'MailAtSign02Icon',     // Campaigns
        'MailSend02Icon',       // Transactional / sending address
        'NodeEditIcon',         // Automations
    ];

    foreach ($shared as $icon) {
        expect($sidebar)->toContain($icon)
            ->and($checklist)->toContain($icon);
    }

    expect($checklist)->not->toContain('MailSend01Icon')
        ->not->toContain('Mail01Icon');
});

test('every getting started step points to its current workflow', function () {
    $source = file_get_contents(
        dirname(__DIR__, 2).'/resources/js/components/getting-started-checklist.tsx',
    );

    expect($source)->toBeString()
        ->toContain('delivery: {')
        ->toContain("from '@/routes/teams/email-provider'")
        ->toContain('teamEmailProviderSettings.url(teamSlug)')
        ->toContain('sender: {')
        ->toContain('teamSenderSettings.url(teamSlug)')
        ->toContain('audience: {')
        ->toContain('audiences.url(teamSlug)')
        ->toContain('subscribers: {')
        ->toContain("from '@/routes/contacts'")
        ->toContain('contacts.url(teamSlug)')
        ->toContain('campaign: {')
        ->toContain('emails.url(teamSlug)')
        ->toContain('automation: {')
        ->toContain('automations.url(teamSlug)');
});

test('the soft wash gradient is theme aware and layered like the sidebar grain', function () {
    $css = file_get_contents(dirname(__DIR__, 2).'/resources/css/app.css');

    expect($css)->toBeString()
        ->toContain('@utility soft-wash {')
        ->toContain('@utility dithered {')
        ->toContain('animation: wash-drift 8s ease-in-out infinite;')
        ->toContain('@keyframes wash-drift {')
        // Unregistered custom properties jump between keyframes, they do not
        // interpolate, so the drift depends on these @property blocks.
        ->toContain('@property --wash-one-x {')
        ->toContain('@property --wash-two-y {')
        ->toContain('@media (prefers-reduced-motion: reduce)')
        ->toContain('filter: url(#ordered-dither);')
        ->toContain('background-color: var(--wash-base);')
        ->toContain('var(--wash-one)')
        ->toContain('var(--wash-two)');

    $light = str($css)->between(':root {', '}')->toString();
    $dark = str($css)->between('.dark {', '}')->toString();

    foreach (['--wash-base', '--wash-one', '--wash-two'] as $token) {
        expect($light)->toContain($token)
            ->and($dark)->toContain($token);
    }
});

test('the step preview dithers its wash with an ordered bayer threshold map', function () {
    $source = file_get_contents(
        dirname(__DIR__, 2).'/resources/js/components/getting-started-checklist.tsx',
    );

    expect($source)->toBeString()
        ->toContain('<OrderedDitherFilter />')
        ->toContain('id="ordered-dither"')
        ->toContain('<feTile in="tile" result="threshold" />')
        ->toContain('operator="arithmetic"')
        ->toContain('type="discrete"')
        // display:none would leave Firefox unable to resolve the filter.
        ->toContain('className="absolute size-0"')
        ->not->toContain('className="hidden"');
});

test('the bayer matrix builds a balanced threshold order', function () {
    $source = file_get_contents(
        dirname(__DIR__, 2).'/resources/js/components/getting-started-checklist.tsx',
    );

    expect($source)->toBeString()
        ->toContain('const DITHER_TILE_SIZE = 8;')
        ->toContain('const DITHER_LEVELS = 10;')
        ->toContain('function buildBayerMatrix(size: number): number[][]')
        ->toContain('crispEdges');
});

test('completed checklist steps use the toaster checkmark in blue', function () {
    $source = file_get_contents(
        dirname(__DIR__, 2).'/resources/js/components/getting-started-checklist.tsx',
    );

    // The free Hugeicons tier ships no filled variants, so the solid glyph comes
    // from the same local set the toaster uses.
    expect($source)->toBeString()
        ->toContain("from '@/components/icons/toast-status-icons'")
        ->toContain('<CheckmarkCircleSolidIcon className="size-4 shrink-0 text-info" />')
        ->not->toContain('CheckmarkCircle02Icon');
});
