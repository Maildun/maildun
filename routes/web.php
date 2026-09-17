<?php

use App\Http\Controllers\AudienceAttributeController;
use App\Http\Controllers\AudienceController;
use App\Http\Controllers\AudienceHygieneController;
use App\Http\Controllers\AudienceSettingsController;
use App\Http\Controllers\AutomationController;
use App\Http\Controllers\AvatarController;
use App\Http\Controllers\AwsSesWebhookController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\ContactAudienceController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\ContactExportController;
use App\Http\Controllers\ContactImportController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EmailAttachmentController;
use App\Http\Controllers\EmailController;
use App\Http\Controllers\EmailLinkCheckController;
use App\Http\Controllers\EmailTemplateController;
use App\Http\Controllers\EmailTrackingController;
use App\Http\Controllers\EmailWebViewController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\InstallationController;
use App\Http\Controllers\ListHygieneController;
use App\Http\Controllers\MediaController;
use App\Http\Controllers\MediaSettingsController;
use App\Http\Controllers\PublicSubscribeFormController;
use App\Http\Controllers\SegmentController;
use App\Http\Controllers\SubscribeFormController;
use App\Http\Controllers\SubscriberController;
use App\Http\Controllers\SubscriptionConfirmationController;
use App\Http\Controllers\Teams\TeamInvitationController;
use App\Http\Controllers\TeamSenderVerificationController;
use App\Http\Controllers\TransactionalEmailController;
use App\Http\Controllers\UnsubscribeController;
use App\Http\Middleware\EnsureInstallationIsPending;
use App\Http\Middleware\EnsureTeamMembership;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SetTeamUrlDefaults;
use App\Services\DiceBearAvatarGenerator;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;

Route::get('install', [InstallationController::class, 'show'])
    ->middleware([EnsureInstallationIsPending::class, 'signed:relative', 'throttle:30,1'])
    ->name('install.show');
Route::post('install', [InstallationController::class, 'store'])
    ->middleware([EnsureInstallationIsPending::class, 'signed:relative', 'throttle:5,1'])
    ->name('install.store');
Route::get('install/system', [InstallationController::class, 'system'])
    ->middleware([EnsureInstallationIsPending::class, 'signed:relative', 'throttle:30,1'])
    ->name('install.system.show');
Route::post('install/system', [InstallationController::class, 'testSystem'])
    ->middleware([EnsureInstallationIsPending::class, 'signed:relative', 'throttle:5,1'])
    ->name('install.system.run');

Route::get('/', HomeController::class)->middleware('auth')->name('home');

Route::get('avatars/{style}/{seed}.svg', AvatarController::class)
    ->whereIn('style', DiceBearAvatarGenerator::STYLES)
    ->where('seed', '[A-Za-z0-9-]{1,36}')
    ->middleware(['signed:relative', 'throttle:300,1'])
    ->withoutMiddleware([
        AddLinkHeadersForPreloadedAssets::class,
        AddQueuedCookiesToResponse::class,
        EncryptCookies::class,
        HandleAppearance::class,
        HandleInertiaRequests::class,
        PreventRequestForgery::class,
        SetTeamUrlDefaults::class,
        ShareErrorsFromSession::class,
        StartSession::class,
    ])
    ->name('avatars.show');

Route::get('forms/{subscribeForm}', [PublicSubscribeFormController::class, 'show'])
    ->name('public.subscribe_forms.show');
Route::post('forms/{subscribeForm}/subscribe', [PublicSubscribeFormController::class, 'store'])
    ->middleware('throttle:public-subscribe')
    ->name('public.subscribe_forms.store');
Route::get('confirm/{subscriber}', [SubscriptionConfirmationController::class, 'confirm'])
    ->middleware(['inbox-signed', 'throttle:10,1'])
    ->name('public.subscribe.confirm');
Route::get('sender-verifications/{teamSender}', TeamSenderVerificationController::class)
    ->whereUuid('teamSender')
    ->middleware(['signed', 'throttle:10,1'])
    ->name('team-senders.verify');

Route::get('track/emails/{delivery}/open.gif', [EmailTrackingController::class, 'open'])
    ->middleware('inbox-signed')
    ->name('emails.track.open');
Route::get('track/emails/{delivery}/links/{link}', [EmailTrackingController::class, 'click'])
    ->middleware('inbox-signed')
    ->name('emails.track.click');
Route::get('unsubscribe/test', [UnsubscribeController::class, 'showTest'])
    ->name('public.unsubscribe.test');
Route::get('view/test', [EmailWebViewController::class, 'test'])
    ->name('public.web_view.test');
Route::get('unsubscribe/{delivery}', [UnsubscribeController::class, 'show'])
    ->middleware('inbox-signed')
    ->name('public.unsubscribe.show');
Route::post('unsubscribe/{delivery}', [UnsubscribeController::class, 'store'])
    ->middleware(['inbox-signed', 'throttle:public-unsubscribe'])
    ->name('public.unsubscribe.store');
// Automation mail has no delivery row to key an opt-out to, so it links the
// subscriber instead. Two segments, so it cannot collide with the delivery
// routes above. unsubscribe/* is already CSRF exempt in bootstrap/app.php.
Route::get('unsubscribe/s/{subscriber}', [UnsubscribeController::class, 'showForSubscriber'])
    ->middleware('inbox-signed')
    ->name('public.unsubscribe.subscriber.show');
Route::post('unsubscribe/s/{subscriber}', [UnsubscribeController::class, 'storeForSubscriber'])
    ->middleware(['inbox-signed', 'throttle:public-unsubscribe'])
    ->name('public.unsubscribe.subscriber.store');

// The hosted copy behind {{ web_view_url }}. Campaign deliveries take one
// segment and the other kinds two, so a uuid can never land on the wrong
// route: the same split as unsubscribe/{delivery} and unsubscribe/s/*.
Route::get('view/{delivery}', [EmailWebViewController::class, 'campaign'])
    ->middleware('inbox-signed')
    ->name('public.web_view.show');
Route::get('view/t/{delivery}', [EmailWebViewController::class, 'transactional'])
    ->middleware('inbox-signed')
    ->name('public.web_view.transactional.show');
Route::get('view/a/{delivery}', [EmailWebViewController::class, 'automation'])
    ->middleware('inbox-signed')
    ->name('public.web_view.automation.show');

Route::post('webhooks/aws/ses', AwsSesWebhookController::class)
    ->name('webhooks.aws.ses');

Route::prefix('{current_team}')
    ->middleware(['auth', 'verified', EnsureTeamMembership::class])
    ->group(function () {
        Route::get('dashboard', DashboardController::class)->name('dashboard');

        Route::scopeBindings()->group(function () {
            Route::get('list-hygiene', [ListHygieneController::class, 'index'])
                ->name('list_hygiene.index');
            Route::delete('list-hygiene/unconfirmed', [ListHygieneController::class, 'destroyUnconfirmed'])
                ->name('list_hygiene.unconfirmed.destroy');
            Route::delete('list-hygiene/inactive', [ListHygieneController::class, 'destroyInactive'])
                ->name('list_hygiene.inactive.destroy');

            Route::post('audiences/{audience}/imports', [ContactImportController::class, 'store'])
                ->name('audiences.imports.store');
            Route::get('audiences/{audience}/exports/{format}', [ContactExportController::class, 'audience'])
                ->whereIn('format', ['csv', 'xls'])
                ->name('audiences.exports.show');
            Route::resource('audiences', AudienceController::class)
                ->only(['index', 'store', 'show', 'update', 'destroy']);

            Route::post('contacts/imports', [ContactImportController::class, 'store'])
                ->name('contacts.imports.store');
            Route::get('contacts/exports/{format}', [ContactExportController::class, 'contacts'])
                ->whereIn('format', ['csv', 'xls'])
                ->name('contacts.exports.show');
            Route::get('contacts/lookup', [ContactController::class, 'lookup'])
                ->name('contacts.lookup');
            Route::resource('contacts', ContactController::class)
                ->only(['index', 'store', 'show', 'update', 'destroy']);
            Route::post('contacts/{contact}/audiences', [ContactAudienceController::class, 'store'])
                ->name('contacts.audiences.store');
            Route::delete('contacts/{contact}/audiences/{subscriber}', [ContactAudienceController::class, 'destroy'])
                ->name('contacts.audiences.destroy');
            Route::resource('companies', CompanyController::class)
                ->only(['index', 'store', 'show', 'update', 'destroy'])
                ->whereUuid('company');
            Route::get('audiences/{audience}/edit', [AudienceSettingsController::class, 'general'])
                ->name('audiences.edit');
            Route::get('audiences/{audience}/settings/sender', [AudienceSettingsController::class, 'sender'])
                ->name('audiences.settings.sender');
            Route::get('audiences/{audience}/settings/notifications', [AudienceSettingsController::class, 'notifications'])
                ->name('audiences.settings.notifications');
            Route::get('audiences/{audience}/settings/double-opt-in', [AudienceSettingsController::class, 'doubleOptIn'])
                ->name('audiences.settings.double-opt-in');
            Route::get('audiences/{audience}/settings/attributes', [AudienceSettingsController::class, 'attributes'])
                ->name('audiences.settings.attributes');
            Route::get('audiences/{audience}/settings/landing-pages', [AudienceSettingsController::class, 'landingPages'])
                ->name('audiences.settings.landing-pages');
            Route::get('audiences/{audience}/settings/hygiene', [AudienceHygieneController::class, 'index'])
                ->name('audiences.settings.hygiene');
            Route::delete('audiences/{audience}/settings/hygiene/unconfirmed', [AudienceHygieneController::class, 'destroyUnconfirmed'])
                ->name('audiences.settings.hygiene.unconfirmed.destroy');
            Route::delete('audiences/{audience}/settings/hygiene/inactive', [AudienceHygieneController::class, 'destroyInactive'])
                ->name('audiences.settings.hygiene.inactive.destroy');
            Route::get('audiences/{audience}/settings/danger', [AudienceSettingsController::class, 'danger'])
                ->name('audiences.settings.danger');

            Route::resource('audiences.attributes', AudienceAttributeController::class)
                ->only(['store', 'update', 'destroy'])
                ->parameters(['attributes' => 'audience_attribute']);

            Route::patch('audiences/{audience}/subscribers/bulk-unsubscribe', [SubscriberController::class, 'bulkUnsubscribe'])
                ->name('audiences.subscribers.bulk-unsubscribe');
            Route::delete('audiences/{audience}/subscribers/bulk-destroy', [SubscriberController::class, 'bulkDestroy'])
                ->name('audiences.subscribers.bulk-destroy');
            Route::resource('audiences.subscribers', SubscriberController::class)
                ->only(['show', 'store', 'update', 'destroy']);
            Route::patch('audiences/{audience}/subscribers/{subscriber}/unsubscribe', [SubscriberController::class, 'unsubscribe'])
                ->name('audiences.subscribers.unsubscribe');
            Route::patch('audiences/{audience}/subscribers/{subscriber}/resubscribe', [SubscriberController::class, 'resubscribe'])
                ->name('audiences.subscribers.resubscribe');

            Route::resource('audiences.segments', SegmentController::class)
                ->only(['store', 'show', 'update', 'destroy']);

            Route::post('audiences/{audience}/subscribe-forms', [SubscribeFormController::class, 'store'])
                ->name('audiences.subscribe_forms.store');
            Route::get('audiences/{audience}/subscribe-forms/{subscribeForm}/edit', [SubscribeFormController::class, 'edit'])
                ->name('audiences.subscribe_forms.edit');
            Route::patch('audiences/{audience}/subscribe-forms/{subscribeForm}', [SubscribeFormController::class, 'update'])
                ->name('audiences.subscribe_forms.update');
            Route::delete('audiences/{audience}/subscribe-forms/{subscribeForm}', [SubscribeFormController::class, 'destroy'])
                ->name('audiences.subscribe_forms.destroy');
            Route::patch('audiences/{audience}/subscribe-forms/{subscribeForm}/publish', [SubscribeFormController::class, 'publish'])
                ->name('audiences.subscribe_forms.publish');
            Route::patch('audiences/{audience}/subscribe-forms/{subscribeForm}/unpublish', [SubscribeFormController::class, 'unpublish'])
                ->name('audiences.subscribe_forms.unpublish');

            Route::resource('emails', EmailController::class)
                ->only(['index', 'store', 'show', 'edit', 'update', 'destroy']);
            Route::get('emails/{email}/check-links', EmailLinkCheckController::class)
                ->middleware('throttle:10,1')
                ->name('emails.check-links');
            Route::get('emails/{email}/compose-preview', [EmailController::class, 'composePreview'])
                ->name('emails.compose-preview');
            Route::get('emails/{email}/preview-and-send', [EmailController::class, 'previewAndSend'])
                ->name('emails.preview-and-send');
            Route::post('emails/{email}/attachments', [EmailAttachmentController::class, 'store'])
                ->name('emails.attachments.store');
            Route::delete('emails/{email}/attachments/{attachment}', [EmailAttachmentController::class, 'destroy'])
                ->name('emails.attachments.destroy');
            Route::get('emails/{email}/recipients', [EmailController::class, 'recipients'])
                ->name('emails.recipients');
            Route::get('emails/{email}/links', [EmailController::class, 'links'])
                ->name('emails.links');
            Route::get('emails/{email}/preview', [EmailController::class, 'preview'])
                ->name('emails.preview');
            Route::post('emails/{email}/send', [EmailController::class, 'send'])
                ->name('emails.send');
            Route::post('emails/{email}/retry', [EmailController::class, 'retry'])
                ->name('emails.retry');
            Route::post('emails/{email}/deliveries/{delivery}/retry', [EmailController::class, 'retryDelivery'])
                ->name('emails.deliveries.retry');
            Route::post('emails/{email}/test', [EmailController::class, 'sendTest'])
                ->middleware('throttle:6,1')
                ->name('emails.test');

            Route::resource('automations', AutomationController::class)
                ->only(['index', 'store', 'edit', 'update', 'destroy']);
            Route::get('automations/{automation}/activity', [AutomationController::class, 'activity'])
                ->name('automations.activity');
            Route::post('automations/{automation}/activate', [AutomationController::class, 'activate'])
                ->name('automations.activate');
            Route::post('automations/{automation}/pause', [AutomationController::class, 'pause'])
                ->name('automations.pause');
            Route::post('automations/{automation}/regenerate-token', [AutomationController::class, 'regenerateToken'])
                ->name('automations.regenerate-token');

            Route::resource('transactional-emails', TransactionalEmailController::class)
                ->parameters(['transactional-emails' => 'transactionalEmail'])
                ->names('transactional_emails')
                ->only(['index', 'store', 'edit', 'update', 'destroy']);
            Route::post('transactional-emails/{transactionalEmail}/publish', [TransactionalEmailController::class, 'publish'])
                ->name('transactional_emails.publish');
            Route::post('transactional-emails/{transactionalEmail}/unpublish', [TransactionalEmailController::class, 'unpublish'])
                ->name('transactional_emails.unpublish');
            Route::post('transactional-emails/{transactionalEmail}/duplicate', [TransactionalEmailController::class, 'duplicate'])
                ->name('transactional_emails.duplicate');
            Route::post('transactional-emails/{transactionalEmail}/test', [TransactionalEmailController::class, 'sendTest'])
                ->middleware('throttle:6,1')
                ->name('transactional_emails.test');

            Route::get('media/settings', [MediaSettingsController::class, 'edit'])
                ->name('media.settings.edit');
            Route::patch('media/settings', [MediaSettingsController::class, 'update'])
                ->name('media.settings.update');
            Route::resource('media', MediaController::class)
                ->parameters(['media' => 'media'])
                ->only(['index', 'store', 'update', 'destroy']);

            Route::get('email-templates', [EmailTemplateController::class, 'index'])
                ->name('email_templates.index');
            Route::post('email-templates', [EmailTemplateController::class, 'store'])
                ->name('email_templates.store');
            Route::get('email-templates/{emailTemplate}/edit', [EmailTemplateController::class, 'edit'])
                ->name('email_templates.edit');
            Route::match(['put', 'patch'], 'email-templates/{emailTemplate}', [EmailTemplateController::class, 'update'])
                ->name('email_templates.update');
            Route::delete('email-templates/{emailTemplate}', [EmailTemplateController::class, 'destroy'])
                ->name('email_templates.destroy');
        });

        // Duplicating reaches starter templates, which belong to no team and so
        // cannot be resolved through a scoped binding.
        Route::post('email-templates/{emailTemplate}/duplicate', [EmailTemplateController::class, 'duplicate'])
            ->name('email_templates.duplicate');
    });

Route::middleware(['auth'])->group(function () {
    Route::post('invitations/{invitation}/accept', [TeamInvitationController::class, 'accept'])->name('invitations.accept');
    Route::delete('invitations/{invitation}', [TeamInvitationController::class, 'decline'])->name('invitations.decline');
});

require __DIR__.'/settings.php';
