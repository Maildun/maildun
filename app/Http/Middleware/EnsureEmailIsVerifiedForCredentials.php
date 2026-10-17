<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Turns unverified users away from Fortify's passkey and two-factor enrolment routes.
 *
 * Anyone can sign up with an address they do not own, and they know the
 * password they chose, so they can pass password confirmation. A passkey or
 * TOTP secret added then would outlive the real owner's password reset and
 * hand the account back to them once the owner verifies it. Fortify registers
 * these routes itself, and its auth_middleware also guards the verification
 * routes, so `verified` cannot be added there; this runs from
 * `fortify.middleware` and matches the enrolment routes by name instead.
 */
class EnsureEmailIsVerifiedForCredentials
{
    /**
     * The Fortify routes that add a way to sign in or to lock the account.
     *
     * @var list<string>
     */
    private const array CREDENTIAL_ROUTES = [
        'passkey.registration-options',
        'passkey.store',
        'two-factor.enable',
        'two-factor.confirm',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->routeIs(...self::CREDENTIAL_ROUTES)) {
            return $next($request);
        }

        $user = $request->user();

        if (! $user instanceof MustVerifyEmail || $user->hasVerifiedEmail()) {
            return $next($request);
        }

        return $request->expectsJson()
            ? response()->json(['message' => __('Verify your email address before adding a passkey or two-factor authentication.')], 403)
            : to_route('verification.notice');
    }
}
