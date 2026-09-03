<?php

test('the team brand theme stays scoped to subscribe forms', function () {
    $root = dirname(__DIR__, 2);
    $app = file_get_contents($root.'/resources/js/app.tsx');
    $providers = file_get_contents($root.'/resources/js/components/app-providers.tsx');
    $subscribeFormView = file_get_contents($root.'/resources/js/components/subscribe-form-view.tsx');
    $themeHelper = file_get_contents($root.'/resources/js/lib/team-brand-theme.ts');
    $css = file_get_contents($root.'/resources/css/app.css');

    expect($app)->toBeString()
        ->toContain('withApp(app)')
        ->toContain('<AppProviders>{app}</AppProviders>')
        ->not->toContain('currentTeam={page.props.currentTeam}');

    expect($providers)->toBeString()
        ->not->toContain('TeamBrandThemeProvider')
        ->not->toContain('currentTeam');

    expect($subscribeFormView)->toBeString()
        ->toContain("'data-subscribe-form-theme': ''")
        ->toContain("'data-team-brand-color': form.theme.color")
        ->toContain('data-subscribe-date-trigger')
        ->toContain('style={subscribeFormThemeStyle(theme)}')
        ->toContain("from '@/lib/team-brand-theme'")
        ->not->toContain('useAppearance');

    expect($themeHelper)->toBeString()
        ->toContain("'--subscribe-form-primary-light': palette.light")
        ->toContain("'--font-sans':")
        ->and($css)->toBeString()
        ->toContain('[data-subscribe-form-theme][data-team-brand-font]')
        ->toContain('.dark [data-subscribe-form-theme]')
        ->toContain("[data-subscribe-form-theme][data-team-input-style='soft']")
        ->toContain('[data-subscribe-date-trigger]')
        ->not->toContain('html[data-team-input-style');

    expect(file_exists($root.'/resources/js/components/team-brand-theme-provider.tsx'))
        ->toBeFalse();
});
