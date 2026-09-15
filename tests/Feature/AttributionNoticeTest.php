<?php

use App\Models\User;

test('the source url defaults to the upstream repository', function () {
    expect(config('attribution.source_url'))->toBe('https://github.com/abduns/maildun');
});

test('operators can point the attribution notice at their own source', function () {
    config()->set('attribution.source_url', 'https://git.example.com/fork/maildun');

    $this->actingAs(User::factory()->create())
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->where('attribution.sourceUrl', 'https://git.example.com/fork/maildun')
        );
});

test('the attribution notice reaches unauthenticated visitors', function () {
    $this->get(route('login'))
        ->assertInertia(fn ($page) => $page
            ->where('attribution.sourceUrl', config('attribution.source_url'))
        );
});

test('the attribution badge credits Maildun regardless of the configured app name', function () {
    $badge = file_get_contents(dirname(__DIR__, 2).'/resources/js/components/attribution-badge.tsx');

    expect($badge)->toBeString()
        ->toContain("const PRODUCT_NAME = 'Maildun';")
        ->toContain('Powered by')
        ->toContain('section 7(b)')
        ->not->toContain('app.name')
        ->not->toContain('props.name');
});

test('the attribution badge renders on every public page', function () {
    $root = dirname(__DIR__, 2);

    $surfaces = [
        'resources/js/pages/unsubscribe/show.tsx',
        'resources/js/pages/subscribe-forms/confirmed.tsx',
    ];

    foreach ($surfaces as $surface) {
        expect(file_get_contents($root.'/'.$surface))
            ->toBeString()
            ->toContain("import { AttributionBadge } from '@/components/attribution-badge';")
            ->toContain('<AttributionBadge');
    }

    expect(file_get_contents($root.'/resources/js/components/subscribe-form-view.tsx'))
        ->toBeString()
        ->toContain('data-test="subscribe-form-powered-by"')
        ->toContain('Powered by')
        ->toContain('Maildun')
        ->toContain('usePage().props.attribution?.sourceUrl')
        ->toContain('enabled={!completed}');
});

test('the license keeps the AGPL section 7 additional terms above a verbatim AGPL', function () {
    $license = file_get_contents(dirname(__DIR__, 2).'/LICENSE');

    expect($license)->toBeString()
        ->toContain('ADDITIONAL TERMS UNDER SECTION 7')
        ->toContain('Powered by Maildun')
        ->toContain('Copyright (C) 2026 Dunn');

    $agpl = substr($license, strpos($license, '                    GNU AFFERO GENERAL PUBLIC LICENSE'));

    expect(hash('sha256', $agpl))
        ->toBe('0d96a4ff68ad6d4b6f1f30f713b18d5184912ba8dd389f86aa7710db079abcb0');
});
