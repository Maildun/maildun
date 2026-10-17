<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Symfony\Component\HttpFoundation\Response;

/**
 * Passport registers its authorization routes itself, so they cannot carry
 * the `verified` middleware. An unverified account must not approve an MCP
 * client: anyone can register with an address they do not own, and the token
 * would keep working after the real owner takes the account back.
 */
class RequireVerifiedEmailForOAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($request->routeIs('passport.authorizations.*', 'passport.device.authorizations.*')
            && $user instanceof MustVerifyEmail
            && ! $user->hasVerifiedEmail()) {
            return $request->expectsJson()
                ? response()->json(['message' => __('Your email address is not verified.')], 403)
                : Redirect::guest(route('verification.notice'));
        }

        return $next($request);
    }
}
