<?php

namespace App\Services;

use App\Enums\GoogleAuthIntent;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Laravel\Socialite\Socialite;
use Laravel\Socialite\Two\AbstractProvider;
use Symfony\Component\HttpFoundation\Response;

/**
 * "Continue with Google": whether it is offered, and the round trip to
 * Google's account chooser.
 *
 * The routes stay registered either way so Wayfinder can generate their
 * frontend helpers at build time; every Google action answers 404 while
 * GOOGLE_CLIENT_ID or GOOGLE_CLIENT_SECRET is empty.
 */
final class GoogleSignIn
{
    /**
     * Session key holding why the browser was sent to Google until the callback.
     */
    public const string SessionKey = 'google_auth';

    private const string Driver = 'google';

    /**
     * Determine whether the instance has Google OAuth credentials.
     *
     * Read at call time rather than cached, so the switch follows config().
     */
    public function isEnabled(): bool
    {
        return filled(config('services.google.client_id'))
            && filled(config('services.google.client_secret'));
    }

    /**
     * Remember the intent and send the browser to Google's account chooser.
     *
     * Inertia::location() answers an Inertia visit with a 409 the client turns
     * into a full page load, and any other request with a plain redirect.
     */
    public function redirect(Request $request, GoogleAuthIntent $intent, ?string $invitationCode = null): Response
    {
        $request->session()->put(self::SessionKey, [
            'intent' => $intent->value,
            'invitation' => $intent === GoogleAuthIntent::Login ? $invitationCode : null,
        ]);

        /** @var AbstractProvider $provider */
        $provider = Socialite::driver(self::Driver);

        return Inertia::location(
            $provider->with(['prompt' => 'select_account'])->redirect()->getTargetUrl(),
        );
    }

    /**
     * Take the intent recorded by redirect() out of the session.
     *
     * Pulling it makes every callback single use: a replayed or forged
     * callback finds nothing to resume.
     *
     * @return array{intent: GoogleAuthIntent, invitation: string|null}|null
     */
    public function pullPendingAuthentication(Request $request): ?array
    {
        $pending = $request->session()->pull(self::SessionKey);

        if (! is_array($pending) || ! is_string($pending['intent'] ?? null)) {
            return null;
        }

        $intent = GoogleAuthIntent::tryFrom($pending['intent']);

        if ($intent === null) {
            return null;
        }

        $invitation = $pending['invitation'] ?? null;

        return [
            'intent' => $intent,
            'invitation' => $intent === GoogleAuthIntent::Login && is_string($invitation) ? $invitation : null,
        ];
    }

    /**
     * Exchange the callback's authorization code for the Google profile.
     *
     * Socialite checks the state parameter against the session first, and
     * throws when it is missing or does not match.
     */
    public function user(): SocialiteUser
    {
        return Socialite::driver(self::Driver)->user();
    }
}
