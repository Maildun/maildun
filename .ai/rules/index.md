# Project Rules Index

Before planning or editing, find the row whose globs match the file's path and read that rule file.

| Applies to | Rule file |
| --- | --- |
| resources/js/pages/audiences/**,app/Http/Controllers/*Audience*.php,app/Actions/Audiences/*.php | .ai/rules/actions-audiences.md |
| app/Jobs/SendEmailDelivery.php,app/Actions/Emails/** | .ai/rules/actions-emails.md |
| app/{Actions,Http,Services}/**/*Contact*.php | .ai/rules/actions-http-services.md |
| app/{Actions,Jobs,Services,Mail,Http/Controllers}/** | .ai/rules/actions-jobs-services-mail-http-controllers.md |
| app/Http/Controllers/Teams/TeamController.php,app/Actions/Teams/ResolveWorkspaceSwitchDestination.php,resources/js/components/team-switcher.tsx | .ai/rules/actions-teams-js-components.md |
| app/Actions/Emails/** | .ai/rules/app-actions-emails.md |
| {app/Console/Commands/*Storage*.php,config/filesystems.php,.env.example} | .ai/rules/app-console-commands.md |
| app/Models/{User,Team,Audience,Subscriber}.php | .ai/rules/app-models.md |
| resources/js/layouts/app/app-sidebar-layout.tsx, resources/js/layouts/app/** | .ai/rules/app.md |
| resources/js/pages/audiences/**,app/Http/Controllers/*Audience*.php,resources/js/layouts/audiences/** | .ai/rules/audiences-http-controllers.md |
| resources/js/pages/audiences/**, resources/js/pages/audiences/show.tsx | .ai/rules/audiences.md |
| app/Actions/Automations/**,app/Http/Controllers/Automation*.php | .ai/rules/automations-http-controllers.md |
| app/Console/Commands/ResumeAutomationRunsCommand.php,app/Actions/Automations/**,app/Jobs/ProcessAutomationRun.php | .ai/rules/automations-jobs.md |
| bootstrap/app.php | .ai/rules/bootstrap.md |
| {app/Console/Commands/ResumeEmailDeliveriesCommand.php,app/Actions/Emails/FinalizeEmailSend.php,routes/console.php} | .ai/rules/commands-actions-emails.md |
| app/{Actions/Automations,Jobs,Models,Console/Commands}/**,database/migrations/*automation_run_steps* | .ai/rules/commands-migrations.md |
| app/Models/Segment.php app/Http/Controllers/SegmentController.php app/Actions/Audiences/*.php app/Console/Commands/SyncSegmentsCommand.php | .ai/rules/commands.md |
| resources/js/pages/companies/show.tsx,app/Http/Controllers/CompanyController.php | .ai/rules/companies-http-controllers.md |
| resources/js/pages/companies/show.tsx,resources/js/components/contact-hover-card.tsx | .ai/rules/companies-js-components.md |
| resources/js/pages/companies/show.tsx | .ai/rules/companies.md |
| resources/js/components/subscribe-form-view.tsx,resources/js/pages/subscribe-forms/**,resources/js/pages/teams/theme.tsx,resources/js/app.tsx,resources/js/components/app-providers.tsx,resources/css/app.css | .ai/rules/components-css.md |
| resources/js/pages/emails/** resources/js/pages/email-templates/** resources/js/components/{app-sidebar,delete-email-modal,email-template-picker,save-email-as-template-dialog,delete-email-template-modal}.tsx app/Http/Controllers/EmailController.php | .ai/rules/components-http-controllers.md |
| resources/js/components/email-builder-editor.css,resources/js/components/email-builder-editor.tsx, resources/js/components/automation-canvas.tsx, resources/js/components/automation-step-panel.tsx | .ai/rules/components-js-components.md |
| resources/js/components/{preview-width-tabs,email-html-editor,email-builder-editor}.tsx,resources/js/pages/emails/show.tsx | .ai/rules/components-js-pages-emails.md |
| resources/js/components/ui/sidebar.tsx,resources/js/components/nav-search.tsx | .ai/rules/components-ui-js-components.md |
| resources/js/components/ui/dialog.tsx,resources/js/components/ui/command.tsx | .ai/rules/components-ui.md |
| resources/js/components/app-sidebar.tsx, resources/js/components/{user-menu-content,team-switcher,app-header}.tsx, resources/js/components/{app-sidebar,user-menu-content,team-switcher,app-header}.tsx, resources/js/components/user-menu-content.tsx, resources/js/components/{subscriber-stats-chart,sliding-underline-list}.tsx, resources/js/components/filter-menu.tsx, resources/js/components/active-filters.tsx, resources/js/components/subscriber-hover-card.tsx, resources/js/components/{create-team-modal,team-switcher,nav-user,app-header}.tsx, resources/js/components/appearance-tabs.tsx, resources/js/components/getting-started-checklist.tsx, resources/js/components/email-builder-editor.css, resources/js/components/email-report-layout.tsx, resources/js/components/contact-import-dialog.tsx | .ai/rules/components.md |
| app/{Enums,Http/Controllers,Http/Requests,Mcp,Models}/**,resources/js/{components,lib,pages,types}/** | .ai/rules/componentslibpagestypes.md |
| {config/delivery.php,config/horizon.php,app/Jobs/Send*Delivery.php,app/Concerns/ThrottlesEmailDelivery.php} | .ai/rules/concerns.md |
| config/filesystems.php,app/Models/SubscribeForm.php | .ai/rules/config-models.md |
| {config/services.php,config/filesystems.php} | .ai/rules/config.md |
| config/filesystems.php,app/Enums/StorageBackend.php,app/Services/StorageBackendMigrator.php,app/Console/Commands/SyncStorageBackendCommand.php | .ai/rules/console-commands.md |
| resources/js/pages/contacts/**,resources/js/components/contact-hover-card.tsx | .ai/rules/contacts-js-components.md |
| app/{Http/Controllers,Http/Requests,Jobs,Models}/**/*ContactImport*.php,resources/js/components/contact-import-dialog.tsx,resources/js/pages/{contacts,audiences}/** | .ai/rules/contactsaudiences.md |
| app/Models/EmailTemplate.php app/Http/Controllers/EmailTemplateController.php app/Http/Controllers/EmailController.php | .ai/rules/controllers-http-controllers.md |
| app/Models/SubscribeForm.php,app/Http/Controllers/*SubscribeForm*.php,app/Http/Requests/SaveSubscribeFormRequest.php, app/Models/TransactionalEmail.php,app/Http/Controllers/TransactionalEmailController.php,app/Http/Requests/*Transactional* | .ai/rules/controllers-http-requests.md |
| app/{Actions/Emails,Http/Controllers,Jobs,Mail,Services}/** | .ai/rules/controllers-jobs-mail-services.md |
| app/{Actions/Emails,Http/Controllers,Jobs,Models}/** | .ai/rules/controllers-jobs-models.md |
| app/Http/Controllers/EmailController.php,app/Http/Controllers/TransactionalEmailController.php,app/Models/EmailTemplate.php | .ai/rules/controllers-models.md |
| app/Mail/**,app/Http/Controllers/UnsubscribeController.php,app/Providers/AppServiceProvider.php | .ai/rules/controllers-providers.md |
| app/Actions/Teams/**,app/Http/Controllers/Settings/ProfileController.php | .ai/rules/controllers-settings.md |
| app/Http/Requests/Teams/**,app/Http/Controllers/Teams/** | .ai/rules/controllers-teams.md |
| app/Http/Controllers/*Audience*.php, app/Http/Controllers/SegmentController.php, app/Http/Controllers/{EmailController,EmailTemplateController,TransactionalEmailController,AudienceController,MediaController}.php | .ai/rules/controllers.md |
| resources/css/app.css | .ai/rules/css.md |
| database/migrations/** | .ai/rules/database-migrations.md |
| resources/js/{pages/settings,pages/teams,components/delete-user,components/settings-panel}.tsx | .ai/rules/delete-usercomponents.md |
| resources/js/email-builder/App/** | .ai/rules/email-builder-app.md |
| database/seeders/InstallSeeder.php | .ai/rules/email-templates-seeders.md |
| resources/js/pages/email-templates/edit.tsx, resources/js/pages/email-templates/index.tsx | .ai/rules/email-templates.md |
| app/{Actions/Emails,Http/Controllers,Models}/** | .ai/rules/emails-http-controllers-models.md |
| resources/js/pages/emails/**,app/Http/Controllers/EmailController.php | .ai/rules/emails-http-controllers.md |
| app/{Actions/Emails,Jobs}/** | .ai/rules/emails-jobs.md |
| app/{Actions/Emails,Models}/** | .ai/rules/emails-models.md |
| resources/js/lib/email-builder.ts resources/js/components/email-*.tsx resources/js/pages/emails/** | .ai/rules/emails.md |
| app/Console/Commands/ConfigureStorageCommand.php,app/Enums/StorageBackend.php,app/Services/StorageBackendMigrator.php | .ai/rules/enums-services.md |
| {config/filesystems.php,.env.example,app/Console/Commands/*Storage*.php,app/Enums/StorageBackend.php} | .ai/rules/enums.md |
| {routes/settings.php,app/Http/Controllers/Teams/TeamEmailIntegrationController.php,tests/Feature/TeamEmailIntegrationTest.php} | .ai/rules/feature.md |
| components.json, vite.config.ts, {package.json,pnpm-lock.yaml} | .ai/rules/general.md |
| resources/js/pages/**/*.tsx,resources/js/hooks/use-list-filters.ts,resources/js/components/{filter-menu,list-search,active-filters}.tsx | .ai/rules/hooks-js-components.md |
| resources/js/{hooks/use-media-upload.ts,hooks/use-upload-toast.ts,components/ui/toast.tsx} | .ai/rules/hooks-ui.md |
| resources/js/hooks/use-mobile.tsx, resources/js/hooks/use-media-upload.ts, resources/js/hooks/use-current-url.ts | .ai/rules/hooks.md |
| resources/js/pages/emails/**,app/Http/Controllers/EmailController.php,app/Http/Requests/{StoreEmailRequest,UpdateEmailRequest}.php, resources/js/pages/emails/edit.tsx,app/Http/Controllers/EmailController.php,app/Http/Requests/UpdateEmailRequest.php | .ai/rules/http-controllers-http-requests.md |
| app/Http/Controllers/Teams/TeamMemberController.php | .ai/rules/http-controllers-teams.md |
| resources/js/pages/teams/tags.tsx resources/js/components/tag-*.tsx app/Http/Controllers/TagController.php | .ai/rules/http-controllers.md |
| resources/js/components/getting-started-checklist.tsx,resources/js/components/app-sidebar.tsx,app/Actions/Teams/BuildOnboardingChecklist.php,app/Http/Middleware/HandleInertiaRequests.php | .ai/rules/http-middleware.md |
| app/Http/Requests/SaveSubscriberRequest.php | .ai/rules/http-requests.md |
| app/{Jobs,Models,Console/Commands}/** | .ai/rules/jobs-models-console-commands.md |
| app/Actions/Automations/**,app/Jobs/ProcessAutomationRun.php | .ai/rules/jobs.md |
| resources/js/components/subscribe-form-view.tsx,resources/css/app.css | .ai/rules/js-components-css.md |
| resources/js/pages/transactional/**,resources/js/components/{app-sidebar,delete-transactional-email-modal,send-test-transactional-email-dialog}.tsx,app/Http/Controllers/TransactionalEmailController.php | .ai/rules/js-components-http-controllers.md |
| resources/js/components/paginator.tsx,resources/js/components/ui/pagination.tsx | .ai/rules/js-components-ui.md |
| resources/js/pages/audiences/**,resources/js/pages/segments/show.tsx,resources/js/components/subscriber-hover-card.tsx | .ai/rules/js-components.md |
| resources/js/pages/media/**,resources/js/components/media-dropzone.tsx,resources/js/hooks/use-media-upload.ts | .ai/rules/js-hooks.md |
| app/Http/Controllers/Teams/*Email*SettingsController.php,app/Http/Controllers/Teams/TeamSenderSettingsController.php,app/Http/Requests/Teams/SaveTeam*SettingsRequest.php,resources/js/pages/teams/{email,sender,email-provider,email-provider-show}.tsx,resources/js/layouts/settings/layout.tsx,routes/settings.php | .ai/rules/js-layouts-settings.md |
| app/Http/Controllers/EmailTemplateController.php,app/Http/Requests/SaveEmailTemplateRequest.php,resources/js/pages/email-templates/** | .ai/rules/js-pages-email-templates.md |
| app/{Actions/Emails,Jobs,Mail,Http/Controllers}/** resources/js/pages/emails/** config/{mail,services}.php | .ai/rules/js-pages-emails.md |
| app/Models/SubscribeForm.php,app/Http/Controllers/SubscribeFormController.php,app/Jobs/ProcessSubscribeFormImage.php,resources/js/pages/subscribe-forms/** | .ai/rules/js-pages-subscribe-forms.md |
| app/Http/Controllers/Teams/TeamMemberController.php,resources/js/pages/teams/members.tsx | .ai/rules/js-pages-teams.md |
| resources/js/pages/**/*.tsx | .ai/rules/js-pages.md |
| resources/js/**/*.tsx, resources/js/app.tsx | .ai/rules/js.md |
| resources/js/{components/campaign-insights*.tsx,lib/world-map-paths.ts}, resources/js/{components/country-flag.tsx,lib/country-flags.ts,components/campaign-insights*.tsx}, resources/js/{components/client-icon.tsx,lib/brand-icon-paths.ts,components/campaign-insights*.tsx} | .ai/rules/jscomponents.md |
| resources/js/layouts/settings/layout.tsx | .ai/rules/layouts-settings.md |
| resources/js/email-builder/**,resources/js/components/email-builder-editor.tsx,resources/js/lib/email-builder.ts | .ai/rules/lib.md |
| resources/js/pages/list-hygiene/** | .ai/rules/list-hygiene.md |
| {app/Mcp/**,routes/ai.php,.mcp.json} | .ai/rules/mcp.md |
| {config/filesystems.php,app/{Actions/Media,Jobs,Http/Controllers,Models}/**} | .ai/rules/media-jobs-http-controllers-models.md |
| app/Models/Media.php,app/Actions/Media/**,app/Http/Controllers/Media*.php,resources/js/pages/media/**,resources/js/components/media-*.tsx | .ai/rules/media-js-components.md |
| app/Models/Media.php,app/Actions/Media/**,app/Jobs/ProcessMediaImage.php,app/Http/Controllers/Media*.php,resources/js/pages/media/** | .ai/rules/media.md |
| routes/api.php,app/Http/Middleware/AuthenticateAutomationTrigger.php,app/Http/Controllers/AutomationTriggerController.php | .ai/rules/middleware-http-controllers.md |
| {config/fortify.php,app/Http/Middleware/EnsureRegistrationIsOpen.php,app/Providers/FortifyServiceProvider.php} | .ai/rules/middleware-providers.md |
| {app/Console/Commands/InstallCommand.php,app/Actions/Install/**,app/Http/Controllers/InstallationController.php,app/Http/Middleware/EnsureInstallationIsPending.php,app/Services/InstallationState.php,routes/web.php} | .ai/rules/middleware-services.md |
| app/Models/Membership.php app/Concerns/HasTeams.php app/Http/Middleware/EnsureTeamMembership.php | .ai/rules/middleware.md |
| database/migrations/*permission*.php database/migrations/*_grant_*.php | .ai/rules/migrations.md |
| app/Jobs/ProcessMediaImage.php,app/Jobs/ProcessSubscribeFormImage.php,app/Models/SubscribeForm.php,app/Services/StorageBackendMigrator.php | .ai/rules/models-services.md |
| resources/js/pages/audiences/**,app/Http/Controllers/*Audience*.php,app/Models/Audience.php,app/Models/AudienceAttribute.php | .ai/rules/models.md |
| app/Models/Subscriber.php,resources/js/pages/audiences/show.tsx,app/Http/Controllers/SubscriberController.php | .ai/rules/pages-audiences-http-controllers.md |
| resources/js/pages/automations/**,app/Http/Controllers/AutomationController.php | .ai/rules/pages-automations-http-controllers.md |
| resources/js/components/app-sidebar.tsx,resources/js/pages/email-templates/** | .ai/rules/pages-email-templates.md |
| resources/js/pages/emails/edit.tsx, resources/js/pages/emails/preview-and-send.tsx | .ai/rules/pages-emails.md |
| resources/js/components/media-detail-dialog.tsx,resources/js/pages/media/** | .ai/rules/pages-media.md |
| resources/js/pages/settings/**/*.tsx, resources/js/pages/settings/profile.tsx | .ai/rules/pages-settings.md |
| resources/js/pages/subscribe-forms/** | .ai/rules/pages-subscribe-forms.md |
| {app/Models/TeamSender*.php,app/Models/TeamEmailIntegration.php,app/Http/Controllers/Teams/TeamSender*.php,resources/js/pages/teams/{sender,email-provider-show}.tsx,tests/Feature/*Sender*Test.php} | .ai/rules/pages-teams-feature.md |
| resources/js/pages/teams/**/*.tsx, resources/js/pages/teams/theme.tsx, resources/js/pages/teams/edit.tsx, resources/js/pages/teams/members.tsx, resources/js/pages/teams/tags.tsx | .ai/rules/pages-teams.md |
| app/{Models,Services,Http/Controllers/Teams,Http/Requests/Teams}/**/*.php,resources/js/{pages/teams,components}/**/*email-provider*.tsx,tests/**/*EmailIntegration*.php | .ai/rules/pages-teamscomponents.md |
| resources/js/components/active-filters.tsx,resources/js/pages/**/*.tsx | .ai/rules/pages.md |
| resources/js/components/settings-page-header.tsx,resources/js/pages/{settings,teams}/**/*.tsx | .ai/rules/pagessettingsteams.md |
| config/trustedproxy.php,app/Providers/AppServiceProvider.php | .ai/rules/providers.md |
| resources/js/pages/subscribe-forms/**,resources/js/components/subscribe-form-view.tsx,app/Http/Controllers/*SubscribeForm*.php,app/Http/Requests/PublicSubscribeRequest.php,app/Actions/Audiences/SubscribeToAudience.php | .ai/rules/requests-actions-audiences.md |
| app/{Services,Actions/Emails,Actions/Transactional,Actions/Automations,Jobs,Http/Requests,Rules}/** | .ai/rules/requests-rules.md |
| app/{Models,Services,Http/Controllers/Teams,Http/Requests/Teams}/**/*.php | .ai/rules/requests-teams.md |
| app/Models/Tag.php app/Models/Subscriber.php app/Http/Controllers/SubscriberController.php app/Http/Controllers/TagController.php app/Http/Requests/SaveSubscriberRequest.php | .ai/rules/requests.md |
| {routes/ai.php,app/Mcp/**} | .ai/rules/routes-mcp.md |
| routes/web.php | .ai/rules/routes.md |
| routes/settings.php,resources/js/{routes,actions}/** | .ai/rules/routesactions.md |
| {app/Console/Commands/InstallCommand.php,database/seeders/InstallSeeder.php,database/seeders/DatabaseSeeder.php,app/Services/EnvironmentFile.php} | .ai/rules/seeders-services.md |
| database/seeders/**, database/seeders/FullDemoSeeder.php | .ai/rules/seeders.md |
| resources/js/pages/audiences/**,resources/js/pages/segments/show.tsx,resources/js/components/subscriber-hover-card.tsx,app/Http/Controllers/SubscriberController.php | .ai/rules/segments-js-components-http-controllers.md |
| app/{Services/SesFeedbackVerifier.php,Http/Controllers/Teams/TeamEmailIntegrationController.php} | .ai/rules/services-controllers-teams.md |
| app/{Services,Mail}/**,config/mail.php | .ai/rules/services-mail.md |
| app/Services/TeamMailer.php | .ai/rules/services.md |
| resources/js/pages/audiences/settings/sender.tsx,app/Http/Controllers/{AudienceController,AudienceSettingsController}.php,app/Http/Requests/SaveAudienceRequest.php | .ai/rules/settings-http-controllers-http-requests.md |
| {app/Http/Controllers/Settings/SystemCheckController.php,resources/js/pages/settings/system-check.tsx,resources/js/layouts/settings/layout.tsx} | .ai/rules/settings-js-layouts-settings.md |
| app/Http/Controllers/Teams/TeamController.php,routes/settings.php,resources/js/pages/teams/**,resources/js/layouts/settings/layout.tsx, app/Http/Controllers/Teams/TeamMemberController.php,resources/js/pages/teams/**,resources/js/layouts/settings/layout.tsx,routes/settings.php | .ai/rules/settings.md |
| resources/js/pages/{settings,teams}/**/*.tsx | .ai/rules/settingsteams.md |
| {app/Models/SubscribeForm.php,app/Http/Controllers/*SubscribeForm*.php,app/Http/Requests/SaveSubscribeFormRequest.php,resources/js/pages/subscribe-forms/**,resources/js/components/subscribe-form-view.tsx} | .ai/rules/subscribe-forms-js-components.md |
| resources/js/pages/subscribe-forms/**,resources/js/components/subscribe-form-view.tsx,resources/js/layouts/subscribe-forms/** | .ai/rules/subscribe-forms.md |
| resources/js/pages/audiences/subscribers/show.tsx | .ai/rules/subscribers.md |
| {app/Models/TeamSender*.php,app/Models/TeamEmailIntegration.php,app/Http/Controllers/Teams/TeamSender*.php,app/Services/SenderDomainVerifier.php,resources/js/pages/teams/sender.tsx,tests/Feature/*Sender*Test.php} | .ai/rules/teams-feature.md |
| resources/js/pages/teams/members.tsx,resources/js/components/edit-member-modal.tsx | .ai/rules/teams-js-components.md |
| {app/Models/Team.php,app/Services/TeamMailer.php,app/Http/Controllers/Teams/TeamEmailIntegrationController.php,resources/js/pages/teams/email-provider.tsx} | .ai/rules/teams-js-pages-teams.md |
| app/{Services,Http/Requests/Teams,Models}/**/*.php | .ai/rules/teams-models.md |
| app/Models/Team.php app/Http/Controllers/Teams/** | .ai/rules/teams.md |
| app/{Models,Services,Http/Controllers/Teams,Http/Requests/Teams}/**/*.php,database/{migrations,factories}/**/*.php,resources/js/{pages/teams,components}/**/*email-provider*.tsx,routes/settings.php,tests/**/*EmailIntegration*.php | .ai/rules/teamscomponents.md |
| resources/js/pages/{teams,settings}/**/*.tsx | .ai/rules/teamssettings.md |
| {app/Models,app/Services,app/Http/Controllers/Teams,app/Jobs,resources/js/pages/teams,tests/Feature}/**/*.{php,tsx} | .ai/rules/teamstests-feature.md |
| {routes/api.php,app/Http/Middleware/AuthenticateTeamApiKey.php,app/Http/Controllers/Api/**,app/Actions/Transactional/**,app/Actions/Audiences/**} | .ai/rules/transactional-actions-audiences.md |
| resources/js/pages/transactional/**,app/Http/Controllers/TransactionalEmailController.php,app/Http/Requests/{StoreTransactionalEmailRequest,UpdateTransactionalEmailRequest}.php | .ai/rules/transactional-http-controllers-http-requests.md |
| app/Actions/Transactional/**,app/Http/Controllers/TransactionalEmailController.php,resources/js/pages/transactional/** | .ai/rules/transactional.md |
| {resources/js/components/getting-started-checklist.tsx,app/Actions/Teams/BuildOnboardingChecklist.php,app/Http/Middleware/HandleInertiaRequests.php,resources/js/types/onboarding.ts} | .ai/rules/types.md |
| resources/js/components/ui/sidebar.tsx,resources/css/app.css | .ai/rules/ui-css.md |
| resources/css/app.css,resources/js/components/ui/sidebar.tsx,resources/js/components/app-header.tsx | .ai/rules/ui-js-components.md |
| resources/js/components/ui/tabs.tsx, resources/js/components/ui/badge.tsx, resources/js/components/ui/sonner.tsx, resources/js/components/ui/{input,textarea,select,input-otp,combobox,input-group,code-editor}.tsx, resources/js/components/ui/toast.tsx, resources/js/components/ui/button.tsx, resources/js/components/ui/sidebar.tsx | .ai/rules/ui.md |
| app/Mail/**,app/Actions/Emails/**,app/Http/Controllers/UnsubscribeController.php,resources/js/pages/unsubscribe/** | .ai/rules/unsubscribe.md |
| {composer.json,composer.lock,README.md,docs/installation.md,.github/workflows/*.yml}, {package.json,pnpm-lock.yaml,.github/workflows/*.yml,README.md} | .ai/rules/workflows.md |
