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

test('refused retries show the server reason in an error toast', function () {
    $recipients = file_get_contents(dirname(__DIR__, 2).'/resources/js/pages/emails/recipients.tsx');
    $layout = file_get_contents(dirname(__DIR__, 2).'/resources/js/components/email-report-layout.tsx');

    expect($layout)->toBeString()
        ->toContain("title: 'Could not retry failed deliveries.'")
        ->toContain('errors.email ??');

    expect($recipients)->toBeString()
        ->toContain('title: `Could not retry ${recipient.email}.`')
        ->toContain('errors.email ??');
});

test('a sending campaign surfaces failures while it is still in flight', function () {
    $layout = file_get_contents(dirname(__DIR__, 2).'/resources/js/components/email-report-layout.tsx');

    expect($layout)->toBeString()
        ->toContain('data-test="sending-failure-counts"')
        ->toContain('waiting')
        ->toContain('data-test="sending-failures-alert"')
        ->toContain('Sending continues for everyone else.');
});

test('delivery health says SMTP does not report bounces instead of showing zero', function () {
    $report = file_get_contents(dirname(__DIR__, 2).'/resources/js/pages/emails/show.tsx');

    expect($report)->toBeString()
        ->toContain("metrics.delivery_feedback !== 'unavailable'")
        ->toContain('value={feedbackReported ? metrics.bounced : null}')
        ->toContain('Not reported by SMTP');
});

test('campaign and delivery badges come from the shared email status module', function (string $path) {
    $source = file_get_contents(dirname(__DIR__, 2).'/resources/js/'.$path);

    expect($source)->toBeString()
        ->toContain("from '@/lib/email-status'")
        ->not->toMatch('/const (CAMPAIGN_|DELIVERY_)?STATUS_(LABELS|VARIANTS)\b|const (CAMPAIGN|DELIVERY)_LABELS\b/');
})->with([
    'campaign index' => 'pages/emails/index.tsx',
    'report layout' => 'components/email-report-layout.tsx',
    'recipients' => 'pages/emails/recipients.tsx',
    'dashboard' => 'pages/dashboard.tsx',
    'sidebar campaigns' => 'components/nav-campaigns.tsx',
    'subscriber profile' => 'pages/audiences/subscribers/show.tsx',
]);

test('report metrics explain what they count and human engagement is labelled as human', function () {
    $layout = file_get_contents(dirname(__DIR__, 2).'/resources/js/components/email-report-layout.tsx');
    $insights = file_get_contents(dirname(__DIR__, 2).'/resources/js/components/campaign-insights.tsx');
    $links = file_get_contents(dirname(__DIR__, 2).'/resources/js/pages/emails/links.tsx');

    expect($layout)->toBeString()
        ->toContain('aria-label={`About ${label}`}')
        ->toContain('including privacy proxies and security scanners');

    expect($insights)->toBeString()
        ->toContain('label="Human opens"')
        ->toContain('label="Human clicks"');

    expect($links)->toBeString()
        ->toContain('Unique clicks')
        ->toContain('Total clicks')
        ->toContain('Click rate')
        ->toContain('link.unique_clicks');
});

test('a sending campaign says it is safe to leave and announces when it finishes', function () {
    $layout = file_get_contents(dirname(__DIR__, 2).'/resources/js/components/email-report-layout.tsx');
    $sidebar = file_get_contents(dirname(__DIR__, 2).'/resources/js/components/nav-campaigns.tsx');

    expect(preg_replace('/\s+/', ' ', (string) $layout))->toBeString()
        ->toContain('You can leave this page; sending continues in the background');

    expect($layout)->toBeString()
        ->toContain('if (wasActive.current && !isActive) {')
        ->toContain('finished sending.`')
        ->toContain('finished with failures.`');

    expect($sidebar)->toBeString()
        ->toContain('{hasActiveCampaign && <RecentCampaignsPoller />}')
        ->toContain("usePoll(5000, { only: ['recentCampaigns'] }, { mode: 'rest' });");
});

test('a retry reports its own progress and the overview lists the send history', function () {
    $layout = file_get_contents(dirname(__DIR__, 2).'/resources/js/components/email-report-layout.tsx');
    $report = file_get_contents(dirname(__DIR__, 2).'/resources/js/pages/emails/show.tsx');

    expect($layout)->toBeString()
        ->toContain("metrics.run_kind === 'retry' || metrics.run_kind === 'resume'")
        ->toContain("'Retrying failed deliveries'")
        ->toContain('aria-valuenow={runProgress}');

    expect($report)->toBeString()
        ->toContain('{sendRuns.length > 1 && <SendHistory runs={sendRuns} />}')
        ->toContain("'sendRuns',")
        ->toContain("'failureCauses',")
        ->toContain('data-test="campaign-failure-causes"');
});

test('the sending card shows segmented progress, an ETA and stall guidance', function () {
    $layout = file_get_contents(dirname(__DIR__, 2).'/resources/js/components/email-report-layout.tsx');

    expect($layout)->toBeString()
        ->toContain('data-test="sending-progress-bar"')
        ->toContain('percentOf(runProcessed - runFailed, runRecipients)')
        ->toContain('percentOf(runFailed, runRecipients)')
        ->toContain('data-test="sending-eta"')
        ->toContain('data-test="sending-stalled-alert"')
        ->toContain("'No queue worker is running'")
        ->toContain("'Queue workers are paused'");
});

test('a recipient opens a detail sheet with attempts and provider feedback', function () {
    $recipients = file_get_contents(dirname(__DIR__, 2).'/resources/js/pages/emails/recipients.tsx');
    $sheet = file_get_contents(dirname(__DIR__, 2).'/resources/js/components/recipient-delivery-sheet.tsx');

    expect($recipients)->toBeString()
        ->toContain('data-test="open-recipient-detail"')
        ->toContain('<RecipientDeliverySheet');

    expect($sheet)->toBeString()
        ->toContain('showDelivery.url([teamSlug, campaignUuid, deliveryUuid])')
        ->toContain('Send attempts')
        ->toContain('Provider feedback');
});

test('recipients can be searched, counted per tab and exported', function () {
    $recipients = file_get_contents(dirname(__DIR__, 2).'/resources/js/pages/emails/recipients.tsx');

    expect($recipients)->toBeString()
        ->toContain('placeholder="Search recipients"')
        ->toContain('{counts[filter.value].toLocaleString()}')
        ->toContain('data-test="export-recipients"')
        ->toContain('showRecipientExport.url(')
        ->toContain("only: ['recipients', 'filters', 'filterCounts']");
});

test('recipients a failed loader never reached can be queued from the report', function () {
    $layout = file_get_contents(dirname(__DIR__, 2).'/resources/js/components/email-report-layout.tsx');

    expect($layout)->toBeString()
        ->toContain('data-test="unqueued-recipients-alert"')
        ->toContain('data-test="queue-remaining-button"')
        ->toContain("'Sending to the remaining recipients'");
});
