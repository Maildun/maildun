<?php

test('settings layout keeps only the mobile navigation trigger', function () {
    $root = dirname(__DIR__, 2);
    $layout = file_get_contents($root.'/resources/js/layouts/settings/layout.tsx');

    expect($layout)->toBeString()
        ->toContain('<SidebarTrigger className="-ml-1" />')
        ->toContain('md:hidden')
        ->not->toContain('Breadcrumbs')
        ->not->toContain('breadcrumbs = []')
        ->toContain("import type { NavItem } from '@/types'")
        ->toContain('DashboardSquareSettingIcon');
});

test('settings search keeps the requested navigation icon mapping', function () {
    $layout = file_get_contents(dirname(__DIR__, 2).'/resources/js/layouts/settings/layout.tsx');

    expect($layout)->toBeString()
        ->toContain('group.label.toLowerCase().includes(term)')
        ->toContain('className="text-sidebar-foreground/70"')
        ->toContain('icon: SunMoonIcon')
        ->toContain('icon: NewOfficeIcon')
        ->toContain("title: 'API Key'")
        ->toContain('icon: Key01Icon')
        ->toContain('icon: MailEdit02Icon')
        ->toContain('icon: MailAtSign02Icon')
        ->toContain('icon: MailSend02Icon')
        ->toContain('icon: DashboardSquareSettingIcon')
        ->toContain("title: 'System Check'")
        ->toContain('icon: ShieldCheckIcon')
        ->toContain("currentTeam?.role === 'owner'")
        ->not->toContain('PaintBoardIcon')
        ->not->toContain('ApiIcon')
        ->not->toContain('KeyRoundIcon')
        ->not->toContain('Mail01Icon')
        ->not->toContain('MailAtSign01Icon')
        ->not->toContain('MailSetting02Icon')
        ->not->toContain('ColorsIcon');
});

test('profile settings uses an avatar upload trigger and email status badge', function () {
    $profile = file_get_contents(dirname(__DIR__, 2).'/resources/js/pages/settings/profile.tsx');

    expect($profile)->toBeString()
        ->toContain('Camera01Icon')
        ->toContain('group/avatar-upload relative block cursor-pointer')
        ->toContain('group-hover/avatar-upload:opacity-100')
        ->toContain('group-focus-within/avatar-upload:opacity-100')
        ->toContain("import { Badge } from '@/components/ui/badge'")
        ->toContain('<Badge')
        ->toContain('isEmailVerified')
        ->toContain("? 'success'")
        ->toContain("? 'Verified'")
        ->toContain(': \'Unverified\'')
        ->not->toContain('Choose photo')
        ->not->toContain('CheckmarkCircle02Icon');
});

test('appearance settings uses the sliding tabs primitive for color mode', function () {
    $appearanceTabs = file_get_contents(dirname(__DIR__, 2).'/resources/js/components/appearance-tabs.tsx');

    expect($appearanceTabs)->toBeString()
        ->toContain("from '@/components/ui/tabs'")
        ->toContain('<TabsList variant="sliding" aria-label="Color mode">')
        ->toContain('onValueChange={(value) => updateAppearance(value as Appearance)}')
        ->toContain('<TabsTrigger key={value} value={value}>')
        ->not->toContain('aria-pressed')
        ->not->toContain('<button');
});

test('workspace settings mirrors the profile identity controls', function () {
    $workspace = file_get_contents(dirname(__DIR__, 2).'/resources/js/pages/teams/edit.tsx');

    expect($workspace)->toBeString()
        ->not->toContain('eyebrow=')
        ->toContain('title="Workspace settings"')
        ->toContain('Camera01Icon')
        ->toContain('group/logo-upload relative block cursor-pointer rounded-full')
        ->toContain('group-hover/logo-upload:opacity-100')
        ->toContain('group-focus-within/logo-upload:opacity-100')
        ->not->toContain('Choose logo');
});

test('workspace settings omit eyebrow labels and name email builder consistently', function () {
    $root = dirname(__DIR__, 2);
    $layout = file_get_contents($root.'/resources/js/layouts/settings/layout.tsx');
    $email = file_get_contents($root.'/resources/js/pages/teams/email.tsx');

    foreach ([
        'edit',
        'members',
        'tags',
        'email',
        'sender',
        'email-provider',
        'email-provider-show',
        'api',
        'theme',
    ] as $page) {
        $source = file_get_contents($root."/resources/js/pages/teams/{$page}.tsx");

        expect($source)->toBeString()->not->toContain('eyebrow=');
    }

    expect($layout)->toBeString()->toContain("title: 'Email Editor'");
    expect($layout)->toBeString()
        ->toContain("title: 'Sender'")
        ->toContain("from '@/routes/teams/sender'");
    expect($email)->toBeString()
        ->toContain('title="Email Editor"')
        ->toContain('Email Editor · ${team.name}');
});

test('settings page headers render a plain title without an icon', function () {
    $root = dirname(__DIR__, 2);

    $header = file_get_contents($root.'/resources/js/components/settings-page-header.tsx');

    expect($header)->toBeString()
        ->not->toContain('icon')
        ->not->toContain('HugeiconsIcon');

    foreach ([
        'settings/profile',
        'settings/security',
        'settings/appearance',
        'settings/system-check',
        'teams/edit',
        'teams/members',
        'teams/tags',
        'teams/email',
        'teams/sender',
        'teams/email-provider',
        'teams/email-provider-show',
        'teams/api',
        'teams/theme',
    ] as $page) {
        $source = file_get_contents($root."/resources/js/pages/{$page}.tsx");

        preg_match_all('/<SettingsPageHeader\\b[^>]*>/', (string) $source, $usages);

        expect($usages[0])->not->toBeEmpty();

        foreach ($usages[0] as $usage) {
            expect($usage)->not->toContain('icon=');
        }
    }
});

test('account settings pages omit the eyebrow label above the title', function () {
    $root = dirname(__DIR__, 2);

    foreach (['profile', 'security', 'appearance'] as $page) {
        $source = file_get_contents($root."/resources/js/pages/settings/{$page}.tsx");

        expect($source)->toBeString()->not->toContain('eyebrow=');
    }
});

test('members settings uses compact paginated tables', function () {
    $members = file_get_contents(dirname(__DIR__, 2).'/resources/js/pages/teams/members.tsx');

    expect($members)->toBeString()
        ->toContain("from '@/components/ui/table'")
        ->toContain('members.data.map')
        ->toContain('invitations.data.map')
        ->toContain('<ListPagination')
        ->toContain('Rows per page')
        ->toContain('members_per_page')
        ->toContain('invitations_per_page')
        ->toContain('PaginationPrevious')
        ->toContain('PaginationNext')
        ->toContain('p-3 sm:p-4')
        ->toContain('px-5 py-4')
        ->not->toContain("import { Paginator } from '@/components/paginator'")
        ->not->toContain('overflow-hidden rounded-lg border')
        ->not->toContain('member.uuid');
});

test('sender settings lists addresses in a table matching the members and tags density', function () {
    $source = file_get_contents(dirname(__DIR__, 2).'/resources/js/pages/teams/sender.tsx');

    expect($source)->toBeString()
        ->toContain("from '@/components/ui/table'")
        ->toContain('senders.map')
        ->toContain('<div className="p-3 sm:p-4">')
        ->toContain('className="h-12 px-5"')
        ->toContain('className="max-w-0 px-5 py-4"')
        ->toContain('data-test="sender-row"')
        ->toContain('data-test="sender-actions"')
        ->toContain('MoreHorizontalIcon')
        ->not->toContain('Add01Icon')
        ->toContain('data-test="sender-status-badge"')
        ->toContain('data-test="sender-default-badge"')
        ->toContain('<Empty>')
        ->not->toContain('data-test="sender-tag"')
        ->not->toContain('rounded-full px-3 py-1.5');
});

test('the sender name cell keeps the default badge beside the truncating address', function () {
    $source = file_get_contents(dirname(__DIR__, 2).'/resources/js/pages/teams/sender.tsx');

    expect($source)->toBeString()
        ->toContain('<div className="flex min-w-56 items-center gap-3">')
        ->toContain('<p className="truncate font-medium">')
        ->toContain('className="shrink-0"')
        ->not->toContain('<p className="flex items-center gap-2 truncate font-medium">');
});

test('sender dialogs space their sections through the form wrapper', function () {
    $source = file_get_contents(dirname(__DIR__, 2).'/resources/js/pages/teams/sender.tsx');

    expect(substr_count((string) $source, 'className="space-y-6"'))->toBe(3);
    expect(substr_count((string) $source, '<FieldGroup className="gap-5">'))->toBe(2);

    expect($source)->toBeString()
        ->toContain('data-test="add-sender-submit"')
        ->toContain('data-test="save-sender-settings"')
        ->not->toContain('className="w-full sm:w-auto"');
});

test('settings pages share the inset-card workspace, page header, and panel hierarchy', function () {
    $root = dirname(__DIR__, 2);
    $layout = file_get_contents($root.'/resources/js/layouts/settings/layout.tsx');
    $panel = file_get_contents($root.'/resources/js/components/settings-panel.tsx');
    $header = file_get_contents($root.'/resources/js/components/settings-page-header.tsx');
    $deleteUser = file_get_contents($root.'/resources/js/components/delete-user.tsx');
    $theme = file_get_contents($root.'/resources/js/pages/teams/theme.tsx');

    expect($layout)->toBeString()
        ->toContain('max-w-4xl')
        ->toContain('flex min-h-0 flex-1 flex-col p-10')
        ->not->toContain('variant="inset"');

    expect($panel)->toBeString()
        ->toContain("variant?: 'card' | 'inset'")
        ->toContain("variant = 'card'")
        ->toContain("variant === 'card'")
        ->toContain('grainy relative overflow-hidden rounded-xl bg-muted p-1 shadow-inner')
        ->toContain('rounded-lg border bg-card shadow-xs');

    expect($header)->toBeString()
        ->toContain('<h1')
        ->toContain('text-2xl');

    foreach ([
        'settings/profile',
        'settings/security',
        'settings/appearance',
        'settings/system-check',
        'teams/edit',
        'teams/members',
        'teams/tags',
        'teams/email',
        'teams/sender',
        'teams/email-provider',
        'teams/email-provider-show',
        'teams/api',
        'teams/theme',
    ] as $page) {
        $source = file_get_contents($root."/resources/js/pages/{$page}.tsx");

        expect($source)->toBeString()
            ->toContain('SettingsPageHeader')
            ->toContain('flex flex-col gap-8')
            ->toContain('variant="inset"');
    }

    expect($deleteUser)->toBeString()->toContain('variant="inset"');
    expect($theme)->toBeString()
        ->not->toContain('@/components/ui/card')
        ->not->toContain('<Card');
});

test('settings pages do not export unused breadcrumb data', function () {
    $root = dirname(__DIR__, 2);

    foreach ([
        'settings/profile',
        'settings/security',
        'settings/appearance',
        'teams/edit',
        'teams/members',
        'teams/tags',
        'teams/email',
        'teams/sender',
        'teams/email-provider',
        'teams/email-provider-show',
        'teams/theme',
    ] as $page) {
        $source = file_get_contents($root."/resources/js/pages/{$page}.tsx");

        expect($source)->toBeString()
            ->not->toContain('breadcrumbs:');
    }
});

test('sender settings manages verified workspace identities separately from delivery setup', function () {
    $source = file_get_contents(dirname(__DIR__, 2).'/resources/js/pages/teams/sender.tsx');
    $checklist = file_get_contents(dirname(__DIR__, 2).'/resources/js/components/getting-started-checklist.tsx');

    expect($source)->toBeString()
        ->toContain('Only verified senders can be selected as the workspace default')
        ->toContain('Sender domains')
        ->toContain('noreply')
        ->toContain('TXT name')
        ->toContain('TXT value')
        ->toContain('Check DNS')
        ->toContain('Delete domain')
        ->toContain('toggle-sender-domain')
        ->toContain('sender-domain-actions')
        ->toContain('render={<button type="button" />}')
        ->toContain("'Hide' : 'Show'} DNS records for")
        ->not->toContain('toggle-domain-verification')
        ->not->toContain('Hide domain verification')
        ->toContain('A verified domain authorizes this address')
        ->toContain('Addresses are immutable')
        ->toContain('Resend verification')
        ->toContain('Use as default')
        ->not->toContain('title="Sending domain"');

    expect($checklist)->toBeString()
        ->toContain("from '@/routes/teams/sender'")
        ->toContain("action: 'Open sender settings'")
        ->toContain('teamSenderSettings.url(teamSlug)');
});

test('email delivery uses a provider overview and dedicated safe settings pages', function () {
    $root = dirname(__DIR__, 2);
    $overview = file_get_contents($root.'/resources/js/pages/teams/email-provider.tsx');
    $detail = file_get_contents($root.'/resources/js/pages/teams/email-provider-show.tsx');
    $fields = file_get_contents($root.'/resources/js/components/team-email-provider-fields.tsx');
    $sesDocumentation = file_get_contents($root.'/docs/email-delivery/amazon-ses.md');
    $smtpDocumentation = file_get_contents($root.'/docs/email-delivery/smtp.md');

    expect($overview)->toBeString()
        ->toContain('Connect provider')
        ->toContain('email-provider-options')
        ->toContain('connected-email-provider')
        ->toContain('manage-email-provider-')
        ->toContain('Workspace delivery connection')
        ->toContain('Manage connection')
        ->toContain("import { Button } from '@/components/ui/button'")
        ->toContain('nativeButton={false}')
        ->toContain('render={')
        ->not->toContain('buttonVariants')
        ->toContain('Amazon SES')
        ->toContain("option.value === 'ses'")
        ->toContain('Recommended')
        ->toContain("import { create, show } from '@/routes/teams/email-provider'")
        ->toContain('integration.uuid');

    expect($detail)->toBeString()
        ->toContain('email-provider-detail-page')
        ->toContain('delivery-verification-status')
        ->toContain('Delivery overview')
        ->toContain('Last delivery test')
        ->toContain('Verified senders')
        ->toContain('Test delivery')
        ->toContain('From address')
        ->toContain('To address')
        ->not->toContain('Use for sending')
        ->toContain('email-provider-name')
        ->toContain('<TeamEmailProviderFields')
        ->toContain('const hasProviderTrustChanges =')
        ->toContain('isDirty || hasProviderTrustChanges')
        ->toContain('checked={')
        ->toContain('onCheckedChange={')
        ->toContain('setTrustProviderSenders')
        ->toContain('name="trust_provider_senders"')
        ->toContain('FieldLabel htmlFor="trust_provider_senders"')
        ->toContain('!formIsDirty')
        ->toContain('webhookUrl={webhookUrl}')
        ->toContain('sm:flex-row lg:shrink-0')
        ->not->toContain('className="flex size-14')
        ->not->toContain('MailSend02Icon')
        ->not->toContain('Unlink01Icon')
        ->not->toContain('integration.settings.password')
        ->not->toContain('integration.settings.api_key')
        ->not->toContain('integration.settings.token');

    expect($fields)->toBeString()
        ->toContain('smtp_password')
        ->toContain('ses_access_key_id')
        ->toContain('ses_secret_access_key')
        ->toContain('ses_configuration_set')
        ->toContain('ses_sns_topic_arn')
        ->toContain('ses-webhook-url')
        ->toContain('Delivery, Bounce, and Complaint')
        ->toContain('Maildun tracks campaign opens and clicks')
        ->toContain('Stored credential — leave blank to keep it')
        ->not->toContain('sendgrid_api_key')
        ->not->toContain('mailgun_password')
        ->not->toContain('resend_api_key')
        ->not->toContain('postmark_token')
        ->not->toContain('integration.settings.password')
        ->not->toContain('integration.settings.api_key')
        ->not->toContain('integration.settings.token');

    expect($sesDocumentation)->toBeString()
        ->toContain('# Amazon SES email delivery')
        ->toContain('Recommended')
        ->toContain('Delivery, Bounce, and Complaint')
        ->toContain('SNS webhook URL')
        ->toContain('Changing providers');

    expect($smtpDocumentation)->toBeString()
        ->toContain('# SMTP email delivery')
        ->toContain('relay handoff')
        ->toContain('not mark the message as delivered')
        ->toContain('RFC 3461')
        ->toContain('RFC 6650')
        ->toContain('Changing providers');
});

test('create tag is the verb on the tags settings page', function () {
    $source = file_get_contents(dirname(__DIR__, 2).'/resources/js/pages/teams/tags.tsx');

    expect($source)->toBeString()
        ->toContain('Create tag')
        ->not->toContain('New tag');
});
