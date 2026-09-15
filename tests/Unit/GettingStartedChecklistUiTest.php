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

test('the getting started step preview shares the create workspace dot matrix background', function () {
    $source = file_get_contents(
        dirname(__DIR__, 2).'/resources/js/components/getting-started-checklist.tsx',
    );
    $background = file_get_contents(
        dirname(__DIR__, 2).'/resources/js/components/layered-dot-matrix.tsx',
    );

    expect($source)->toBeString()
        ->toContain('className="w-72 overflow-hidden rounded-xl p-2"')
        ->toContain("from '@/components/layered-dot-matrix'")
        ->toContain('<LayeredDotMatrix')
        ->toContain('className="absolute inset-0"')
        ->toContain('cellSize={3}')
        ->toContain('animation={copy.animation}')
        ->not->toContain('OrderedDitherFilter')
        ->not->toContain('soft-wash');

    expect($background)->toBeString()
        ->toContain("from '@/components/dot-matrix'")
        ->toContain('linear-gradient(145deg, #060d1b 5%, #08235d 40%, #367ed4 72%, #d4e8fc)')
        ->toContain('linear-gradient(to bottom, #060d1bf2 0%, #06296945 40%, transparent 65%, var(--background) 100%)');
});

test('each getting started step uses a distinct dot matrix animation', function () {
    $source = file_get_contents(
        dirname(__DIR__, 2).'/resources/js/components/getting-started-checklist.tsx',
    );
    $dotMatrix = file_get_contents(
        dirname(__DIR__, 2).'/resources/js/components/dot-matrix.tsx',
    );

    expect($source)->toBeString()
        ->toContain("animation: 'orbit'")
        ->toContain("animation: 'sweep'")
        ->toContain("animation: 'ripple'")
        ->toContain("animation: 'wave'")
        ->toContain("animation: 'drift'")
        ->toContain("animation: 'pulse'");

    expect($dotMatrix)->toBeString()
        ->toContain('export type DotMatrixAnimation =')
        ->toContain("animation = 'orbit'")
        ->toContain('animation?: DotMatrixAnimation')
        ->toContain('[animation, cellSize]');
});

test('the getting started step preview keeps its artwork icon free', function () {
    $source = file_get_contents(
        dirname(__DIR__, 2).'/resources/js/components/getting-started-checklist.tsx',
    );

    expect($source)->toBeString()
        ->not->toContain("from '@hugeicons-pro/core-solid-rounded'")
        ->not->toContain('icon: typeof')
        ->not->toContain('icon={copy.icon}')
        ->not->toContain('className="relative size-14 text-white drop-shadow-sm"');
});

test('the checklist keeps only its disclosure and completion icons', function () {
    $source = file_get_contents(
        dirname(__DIR__, 2).'/resources/js/components/getting-started-checklist.tsx',
    );

    expect($source)->toBeString()
        ->toContain('icon={ArrowDown01Icon}')
        ->toContain('<CheckmarkCircleSolidIcon')
        ->not->toContain('MailSend02Icon')
        ->not->toContain('MailAtSign02Icon')
        ->not->toContain('UserGroupIcon')
        ->not->toContain('UserAdd01Icon')
        ->not->toContain('NodeEditIcon');
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
