<?php

namespace App\Listeners;

use Illuminate\Auth\Events\Login;
use Illuminate\Auth\SessionGuard;
use Illuminate\Contracts\Auth\Factory as AuthFactory;

/**
 * Bind a session to the password it signed in with.
 *
 * AuthenticateSession only records the hash on a session's first authenticated
 * request. A session that signs in and sends nothing else until after a password
 * reset would then record the new hash and stay signed in.
 */
class StorePasswordHashInSession
{
    public function __construct(private AuthFactory $auth) {}

    public function handle(Login $event): void
    {
        $guard = $this->auth->guard($event->guard);
        $passwordHash = $event->user->getAuthPassword();

        if (! $guard instanceof SessionGuard || ! $passwordHash) {
            return;
        }

        $guard->getSession()->put(
            'password_hash_'.$event->guard,
            $guard->hashPasswordForCookie($passwordHash),
        );
    }
}
