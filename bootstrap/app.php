<?php

use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\PreserveDesignEmptyStrings;
use App\Http\Middleware\RequireVerifiedEmailForOAuth;
use App\Http\Middleware\SetTeamUrlDefaults;
use App\Http\Middleware\ValidateInboxSignature;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Illuminate\Session\Middleware\AuthenticateSession;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->replace(
            ConvertEmptyStringsToNull::class,
            PreserveDesignEmptyStrings::class,
        );

        $middleware->trimStrings(except: [
            'smtp_password',
            'ses_secret_access_key',
            'sendgrid_api_key',
            'mailgun_password',
            'resend_api_key',
            'postmark_token',
        ]);

        $middleware->validateCsrfTokens(except: [
            'forms/*/subscribe',
            'unsubscribe/*',
            'webhooks/aws/ses',
        ]);

        $middleware->web(append: [
            // Signs out every other session once the account password changes:
            // a password update, a reset link, or an admin-generated password.
            AuthenticateSession::class,
            RequireVerifiedEmailForOAuth::class,
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
            SetTeamUrlDefaults::class,
        ]);

        $middleware->alias([
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
            'inbox-signed' => ValidateInboxSignature::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->dontFlash([
            'smtp_password',
            'ses_secret_access_key',
            'sendgrid_api_key',
            'mailgun_password',
            'resend_api_key',
            'postmark_token',
        ]);

        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
