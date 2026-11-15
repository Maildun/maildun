<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Session\Middleware\AuthenticateSession as BaseAuthenticateSession;

/**
 * Signs out every other session once the account password changes, including
 * the first password of an account that had none.
 *
 * The framework middleware skips an account without a password, so its
 * sessions carry no hash and would adopt whichever password arrives later:
 * setting one, or resetting by email to take the account back, would leave
 * every existing session signed in. Such a session is marked instead, and a
 * mark never matches a real password hash.
 */
class AuthenticateSession extends BaseAuthenticateSession
{
    /**
     * What a session stores in place of a password hash while the account has none.
     */
    public const string PASSWORDLESS = 'passwordless';

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): mixed  $next
     */
    public function handle($request, Closure $next): mixed
    {
        if (! $request->hasSession() || ! $request->user() || $request->user()->getAuthPassword()) {
            return parent::handle($request, $next);
        }

        $request->session()->put('password_hash_'.$this->auth->getDefaultDriver(), self::PASSWORDLESS);

        return tap($next($request), function () use ($request): void {
            // The session that set the first password keeps its sign-in.
            if ($this->guard()->user()?->getAuthPassword()) {
                $this->storePasswordHashInSession($request);
            }
        });
    }
}
