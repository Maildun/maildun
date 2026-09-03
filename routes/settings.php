<?php

use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\SecurityController;
use App\Http\Controllers\Settings\SystemCheckController;
use App\Http\Controllers\TagController;
use App\Http\Controllers\Teams\TeamApiKeyController;
use App\Http\Controllers\Teams\TeamController;
use App\Http\Controllers\Teams\TeamEmailIntegrationController;
use App\Http\Controllers\Teams\TeamEmailSettingsController;
use App\Http\Controllers\Teams\TeamInvitationController;
use App\Http\Controllers\Teams\TeamMemberController;
use App\Http\Controllers\Teams\TeamRoleController;
use App\Http\Controllers\Teams\TeamSenderDomainController;
use App\Http\Controllers\Teams\TeamSenderSettingsController;
use App\Http\Controllers\Teams\TeamThemeController;
use App\Http\Middleware\EnsureTeamMembership;
use Illuminate\Auth\Middleware\RequirePassword;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', '/settings/profile');

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::delete('settings/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('settings/security', [SecurityController::class, 'edit'])
        ->middleware(RequirePassword::class)
        ->name('security.edit');

    Route::put('settings/password', [SecurityController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('user-password.update');

    Route::inertia('settings/appearance', 'settings/appearance')->name('appearance.edit');

    Route::get('settings/system-check', [SystemCheckController::class, 'show'])->name('system-check.show');
    Route::post('settings/system-check', [SystemCheckController::class, 'test'])->name('system-check.test');
    Route::post('settings/app-update', [SystemCheckController::class, 'refreshUpdate'])
        ->middleware('throttle:6,1')
        ->name('app-update.refresh');

    Route::get('settings/workspace', [TeamController::class, 'index'])->name('teams.index');
    Route::get('settings/workspace/create', [TeamController::class, 'create'])->name('teams.create');
    Route::post('settings/workspace', [TeamController::class, 'store'])->name('teams.store');

    Route::middleware(EnsureTeamMembership::class)->group(function () {
        Route::get('settings/workspace/{team}', [TeamController::class, 'edit'])->name('teams.edit');
        Route::patch('settings/workspace/{team}', [TeamController::class, 'update'])->name('teams.update');
        Route::delete('settings/workspace/{team}', [TeamController::class, 'destroy'])->name('teams.destroy');
        Route::post('settings/workspace/{team}/switch', [TeamController::class, 'switch'])->name('teams.switch');
        Route::delete('settings/workspace/{team}/leave', [TeamController::class, 'leave'])->name('teams.leave');

        Route::get('settings/workspace/{team}/members', [TeamMemberController::class, 'index'])->name('teams.members.index');
        Route::patch('settings/workspace/{team}/members/{user}', [TeamMemberController::class, 'update'])->name('teams.members.update');
        Route::delete('settings/workspace/{team}/members/{user}', [TeamMemberController::class, 'destroy'])->name('teams.members.destroy');

        Route::scopeBindings()->group(function () {
            Route::get('settings/workspace/{team}/roles', [TeamRoleController::class, 'index'])->name('teams.roles.index');
            Route::post('settings/workspace/{team}/roles', [TeamRoleController::class, 'store'])->name('teams.roles.store');
            Route::patch('settings/workspace/{team}/roles/{workspaceRole}', [TeamRoleController::class, 'update'])->name('teams.roles.update');
            Route::delete('settings/workspace/{team}/roles/{workspaceRole}', [TeamRoleController::class, 'destroy'])->name('teams.roles.destroy');
        });
        Route::middleware([RequirePassword::class, 'throttle:6,1'])->group(function () {
            Route::post('settings/workspace/{team}/members/{user}/generate-password', [TeamMemberController::class, 'generatePassword'])
                ->name('teams.members.generate-password');
            Route::post('settings/workspace/{team}/members/{user}/send-password-reset-link', [TeamMemberController::class, 'sendPasswordResetLink'])
                ->name('teams.members.send-password-reset-link');
        });

        Route::post('settings/workspace/{team}/invitations', [TeamInvitationController::class, 'store'])->name('teams.invitations.store');
        Route::delete('settings/workspace/{team}/invitations/{invitation}', [TeamInvitationController::class, 'destroy'])->name('teams.invitations.destroy');

        Route::get('settings/workspace/{team}/email', [TeamEmailSettingsController::class, 'edit'])->name('teams.email.edit');
        Route::patch('settings/workspace/{team}/email', [TeamEmailSettingsController::class, 'update'])->name('teams.email.update');
        Route::get('settings/workspace/{team}/sender', [TeamSenderSettingsController::class, 'edit'])->name('teams.sender.edit');
        Route::post('settings/workspace/{team}/sender', [TeamSenderSettingsController::class, 'store'])->name('teams.sender.store');
        Route::post('settings/workspace/{team}/sender/domains', [TeamSenderDomainController::class, 'store'])
            ->name('teams.sender.domains.store');
        Route::post('settings/workspace/{team}/sender/domains/{teamSenderDomain}/verify', [TeamSenderDomainController::class, 'verify'])
            ->whereUuid('teamSenderDomain')
            ->middleware('throttle:6,1')
            ->name('teams.sender.domains.verify');
        Route::delete('settings/workspace/{team}/sender/domains/{teamSenderDomain}', [TeamSenderDomainController::class, 'destroy'])
            ->whereUuid('teamSenderDomain')
            ->name('teams.sender.domains.destroy');
        Route::patch('settings/workspace/{team}/sender/{teamSender}', [TeamSenderSettingsController::class, 'update'])
            ->whereUuid('teamSender')
            ->name('teams.sender.update');
        Route::post('settings/workspace/{team}/sender/{teamSender}/verification', [TeamSenderSettingsController::class, 'resend'])
            ->whereUuid('teamSender')
            ->middleware('throttle:6,1')
            ->name('teams.sender.verification.store');
        Route::patch('settings/workspace/{team}/sender/{teamSender}/default', [TeamSenderSettingsController::class, 'makeDefault'])
            ->whereUuid('teamSender')
            ->name('teams.sender.default.update');
        Route::delete('settings/workspace/{team}/sender/{teamSender}', [TeamSenderSettingsController::class, 'destroy'])
            ->whereUuid('teamSender')
            ->name('teams.sender.destroy');
        Route::get('settings/workspace/{team}/email-provider', [TeamEmailIntegrationController::class, 'edit'])->name('teams.email-provider.edit');
        Route::get('settings/workspace/{team}/email-provider/new/{provider}', [TeamEmailIntegrationController::class, 'create'])->name('teams.email-provider.create');
        Route::post('settings/workspace/{team}/email-provider', [TeamEmailIntegrationController::class, 'store'])->name('teams.email-provider.store');
        Route::scopeBindings()->group(function () {
            Route::get('settings/workspace/{team}/email-provider/{emailIntegration}', [TeamEmailIntegrationController::class, 'show'])->name('teams.email-provider.show');
            Route::patch('settings/workspace/{team}/email-provider/{emailIntegration}', [TeamEmailIntegrationController::class, 'update'])
                ->whereUuid('emailIntegration')
                ->name('teams.email-provider.update');
            Route::post('settings/workspace/{team}/email-provider/{emailIntegration}/test', [TeamEmailIntegrationController::class, 'test'])
                ->whereUuid('emailIntegration')
                ->middleware('throttle:6,1')
                ->name('teams.email-provider.test');
            Route::delete('settings/workspace/{team}/email-provider/{emailIntegration}', [TeamEmailIntegrationController::class, 'destroy'])
                ->whereUuid('emailIntegration')
                ->name('teams.email-provider.destroy');
        });
        Route::get('settings/workspace/{team}/theme', [TeamThemeController::class, 'edit'])->name('teams.theme.edit');
        Route::patch('settings/workspace/{team}/theme', [TeamThemeController::class, 'update'])->name('teams.theme.update');
        Route::get('settings/workspace/{team}/api', [TeamApiKeyController::class, 'index'])->name('teams.api.index');
        Route::post('settings/workspace/{team}/api', [TeamApiKeyController::class, 'store'])->name('teams.api.store');
        Route::delete('settings/workspace/{team}/api/{teamApiKey}', [TeamApiKeyController::class, 'destroy'])->name('teams.api.destroy');

        Route::scopeBindings()->group(function () {
            Route::get('settings/workspace/{team}/tags', [TagController::class, 'index'])->name('tags.index');
            Route::post('settings/workspace/{team}/tags', [TagController::class, 'store'])->name('tags.store');
            Route::delete('settings/workspace/{team}/tags/bulk-destroy', [TagController::class, 'bulkDestroy'])
                ->name('tags.bulk-destroy');
            Route::match(['put', 'patch'], 'settings/workspace/{team}/tags/{tag}', [TagController::class, 'update'])->name('tags.update');
            Route::delete('settings/workspace/{team}/tags/{tag}', [TagController::class, 'destroy'])->name('tags.destroy');
        });
    });

    Route::get('settings/teams/{path?}', function (?string $path = null): RedirectResponse {
        $destination = '/settings/workspace'.($path ? "/{$path}" : '');

        return redirect($destination, 301);
    })->where('path', '.*');
});

Route::get('.well-known/passkey-endpoints', function () {
    return response()->json([
        'enroll' => route('security.edit'),
        'manage' => route('security.edit'),
    ]);
})->name('well-known.passkeys');
