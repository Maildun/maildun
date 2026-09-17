<?php

test('campaign delivery has dedicated report pages with performance and responsive preview', function () {
    $report = file_get_contents(dirname(__DIR__, 2).'/resources/js/pages/emails/show.tsx');
    $recipients = file_get_contents(dirname(__DIR__, 2).'/resources/js/pages/emails/recipients.tsx');
    $links = file_get_contents(dirname(__DIR__, 2).'/resources/js/pages/emails/links.tsx');
    $preview = file_get_contents(dirname(__DIR__, 2).'/resources/js/pages/emails/preview.tsx');
    $previewAndSend = file_get_contents(dirname(__DIR__, 2).'/resources/js/pages/emails/preview-and-send.tsx');
    $layout = file_get_contents(dirname(__DIR__, 2).'/resources/js/components/email-report-layout.tsx');

    expect($report)->toBeString()
        ->toContain('activePage="overview"')
        ->not->toContain('CampaignOverviewChart')
        ->toContain('CampaignInsights')
        ->toContain('Delivery health')
        ->toContain('MailRemove01Icon')
        ->toContain('Cancel01Icon')
        ->toContain('Alert02Icon')
        ->toContain('Clock01Icon')
        ->toContain('bg-destructive/10')
        ->not->toContain('<Tabs')
        ->and($recipients)->toContain('activePage="recipients"')
        ->toContain('AvatarImage')
        ->toContain('AvatarFallback')
        ->toContain('RecipientStatusTabs')
        ->toContain('overflow-x-auto overflow-y-hidden')
        ->toContain('Showing {recipients.from}–{recipients.to}')
        ->toContain('of{\' \'}')
        ->toContain('recipients.total} recipients')
        ->toContain('recipient-filter-${filter.value}')
        ->and($links)->toContain('activePage="links"')
        ->and($preview)->toContain('activePage="preview"')
        ->toContain('PreviewWidthTabs')
        ->toContain("from '@/components/preview-width-tabs'")
        ->toContain("previewWidth === 'desktop'")
        ->toContain(': 375')
        ->and($layout)->toContain('setLayoutProps({')
        ->toContain('fullscreen: false')
        ->toContain('aria-current={active ? \'page\' : undefined}')
        ->toContain('recipients.url([teamSlug, campaignUuid])')
        ->toContain('linksRoute.url([teamSlug, campaignUuid])')
        ->toContain('previewRoute.url([teamSlug, campaignUuid])')
        ->toContain('Unique opens')
        ->toContain('Unique clicks')
        ->toContain('UserGroupIcon')
        ->toContain('MailSend01Icon')
        ->toContain('MailOpen01Icon')
        ->toContain('MouseLeftClick01Icon')
        ->toContain('CardAction')
        ->toContain('Amazon SES')
        ->toContain('Mixed providers')
        ->toContain('SMTP is handoff only')
        ->toContain('h-1 rounded-t bg-foreground')
        ->toContain('after:h-1')
        ->toContain('after:bg-border')
        ->toContain('after:translate-y-full')
        ->toContain('hover:after:translate-y-0')
        ->toContain('before:h-px')
        ->toContain('before:bg-border')
        ->toContain('transition-[transform,width,opacity]')
        ->toContain('motion-safe:slide-in-from-bottom-1')
        ->toContain('motion-reduce:transition-none')
        ->toContain('prefetch')
        ->toContain('data-report-page={page.key}')
        ->toContain('nav.scrollLeft')
        ->not->toContain('after:rounded-full')
        ->not->toContain('previewingInactive')
        ->not->toContain('border-b-2')
        ->not->toContain('border-2')
        ->and($previewAndSend)->toContain('data-test="confirm-send-campaign"');
});

test('campaign insights use compact tabbed views for filtered traffic location and technology', function () {
    $source = file_get_contents(
        dirname(__DIR__, 2).'/resources/js/components/campaign-insights.tsx',
    );
    $map = file_get_contents(
        dirname(__DIR__, 2).'/resources/js/components/campaign-insights-map.tsx',
    );
    $mapPaths = file_get_contents(
        dirname(__DIR__, 2).'/resources/js/lib/world-map-paths.ts',
    );
    $flag = file_get_contents(
        dirname(__DIR__, 2).'/resources/js/components/country-flag.tsx',
    );
    $flagUrls = file_get_contents(
        dirname(__DIR__, 2).'/resources/js/lib/country-flags.ts',
    );
    $clientIcon = file_get_contents(
        dirname(__DIR__, 2).'/resources/js/components/client-icon.tsx',
    );
    $brandPaths = file_get_contents(
        dirname(__DIR__, 2).'/resources/js/lib/brand-icon-paths.ts',
    );

    expect($source)->toBeString()
        ->toContain('Human engagement')
        ->toContain('Traffic quality')
        ->toContain('Geography')
        ->toContain('CampaignInsightsMap')
        ->toContain('campaign-insights-geography-tabs')
        ->toContain('<TabsTrigger value="map">Map</TabsTrigger>')
        ->toContain('<TabsTrigger value="country">Country</TabsTrigger>')
        ->toContain('<TabsTrigger value="region">Region</TabsTrigger>')
        ->toContain('<TabsTrigger value="city">City</TabsTrigger>')
        ->toContain('Technology and networks')
        ->toContain('campaign-insights-technology-tabs')
        ->toContain('<TabsTrigger value="client">Client</TabsTrigger>')
        ->toContain('<TabsTrigger value="device">Device</TabsTrigger>')
        ->toContain('<TabsTrigger value="network">Network</TabsTrigger>')
        ->toContain('MetricToggle')
        ->toContain('<TabsTrigger value="opens">Opens</TabsTrigger>')
        ->toContain('<TabsTrigger value="clicks">Clicks</TabsTrigger>')
        ->not->toContain('overflow-x-auto')
        ->not->toContain('ToggleGroup')
        ->toContain('bg-primary/10')
        ->toContain('<ClientIcon label={row.label} />')
        ->toContain('Tablet01Icon')
        ->toContain('ServerStack01Icon')
        ->toContain('HelpCircleIcon')
        ->toContain("kind === 'country' || kind === 'region' || kind === 'city'")
        ->toContain('<CountryFlag code={insightCountryCode(row.key)} />')
        ->toContain("return key.split(':')[0] ?? '';")
        ->toContain("from '@/components/country-flag'")
        ->toContain('insights.attribution.label')
        ->toContain('Privacy-safe tracking')
        ->toContain('InformationCircleIcon')
        ->toContain('lg:items-stretch')
        ->toContain('h-full min-h-[24rem]')
        ->toContain('InsightBar')
        ->toContain('transition-[width]')
        ->toContain('duration-300 ease-out')
        ->not->toContain('starting:w-0')
        ->not->toContain('data-starting-style:translate-y-1')
        ->not->toContain('insightPanelClassName')
        ->and($map)->toBeString()
        ->toContain('campaign-insights-world-map')
        ->toContain('World map shaded by unique human')
        ->toContain('metric: CampaignInsightMetric')
        ->toContain('tabIndex={row ? 0 : undefined}')
        ->toContain('WORLD_MAP_TINY_COUNTRIES')
        ->and($mapPaths)->toBeString()
        ->toContain('Natural Earth data is public domain')
        ->toContain("code: 'US'")
        ->toContain("code: 'GB'")
        ->toContain("code: 'DE'")
        ->toContain("code: 'SG'")
        ->and($flag)->toBeString()
        ->toContain('countryFlagUrl')
        ->toContain('loading="lazy"')
        ->toContain('Globe02Icon')
        ->and($flagUrls)->toBeString()
        ->toContain('github.com/lipis/flag-icons')
        ->toContain("'/node_modules/flag-icons/flags/4x3/*.svg'")
        ->toContain("query: '?url'")
        ->not->toContain('127397')
        ->and($clientIcon)->toBeString()
        ->toContain("['chrome', { icon: ChromeIcon }]")
        ->toContain("['firefox', { brand: 'Firefox' }]")
        ->toContain("['thunderbird', { brand: 'Thunderbird' }]")
        ->toContain("['gmail', { brand: 'Gmail' }]")
        ->toContain("['scanner', { icon: Shield01Icon }]")
        ->toContain('icon={match ? match.icon : BrowserIcon}')
        ->and($brandPaths)->toBeString()
        ->toContain('simpleicons.org')
        ->toContain('CC0 1.0 Universal')
        ->toContain('BRAND_ICON_VIEW_BOX')
        ->toContain('Firefox:')
        ->toContain('Gmail:')
        ->toContain('Thunderbird:');
});

test('campaign overview page does not render an activity chart', function () {
    $source = file_get_contents(
        dirname(__DIR__, 2).'/resources/js/pages/emails/show.tsx',
    );

    expect($source)->toBeString()
        ->toContain('CampaignInsights')
        ->toContain('Delivery health')
        ->not->toContain('CampaignOverviewChart')
        ->not->toContain('campaign-overview-chart');
});
