<?php

namespace App\Http\Controllers;

use App\Actions\Fortify\ResolveGoogleUser;
use App\Enums\GoogleAuthIntent;
use App\Exceptions\GoogleSignInFailed;
use App\Models\User;
use App\Services\GoogleSignIn;
use Exception;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Laravel\Fortify\Contracts\LoginResponse;
use Laravel\Fortify\Events\TwoFactorAuthenticationChallenged;
use Laravel\Fortify\Features;
use Laravel\Socialite\Two\InvalidStateException;
use Laravel\Socialite\Two\User as GoogleUser;
use Symfony\Component\HttpFoundation\Response;

/**
 * "Continue with Google": starts sign-in and password confirmation, and
 * finishes every round trip to Google, including connecting an account from
 * Security settings.
 *
 * The session is never invalidated here: it carries url.intended (an OAuth
 * authorization in progress, for one) and Socialite's state.
 */
class GoogleAuthController extends Controller
{
    public function __construct(private readonly GoogleSignIn $googleSignIn) {}

    /**
     * Send a guest to Google to log in or sign up.
     */
    public function redirect(Request $request): Response
    {
        abort_unless($this->googleSignIn->isEnabled(), 404);

        $invitation = $request->query('invitation');

        return $this->googleSignIn->redirect(
            $request,
            GoogleAuthIntent::Login,
            is_string($invitation) && $invitation !== '' && strlen($invitation) <= 255 ? $invitation : null,
        );
    }

    /**
     * Confirm the signed-in user's identity with their connected Google
     * account instead of a password.
     */
    public function confirm(Request $request): Response
    {
        abort_unless($this->googleSignIn->isEnabled(), 404);

        if ($request->user()->google_id === null) {
            return to_route('password.confirm')
                ->withErrors(['google' => __('Your account is not connected to a Google account.')]);
        }

        return $this->googleSignIn->redirect($request, GoogleAuthIntent::Confirm);
    }

    /**
     * Finish the round trip for whichever flow sent the browser to Google.
     *
     * Logging in is only for guests, while connecting and confirming are only
     * for the signed-in user, so past the first checks a guest is always
     * logging in.
     */
    public function callback(Request $request, ResolveGoogleUser $resolveGoogleUser): Response
    {
        abort_unless($this->googleSignIn->isEnabled(), 404);

        $pending = $this->googleSignIn->pullPendingAuthentication($request);

        if ($pending === null) {
            return to_route('login')->withErrors(['google' => GoogleSignInFailed::generic()->getMessage()]);
        }

        ['intent' => $intent, 'invitation' => $invitation] = $pending;
        $user = $request->user();

        if ($intent === GoogleAuthIntent::Login && $user !== null) {
            return to_route('home');
        }

        if ($intent !== GoogleAuthIntent::Login && $user === null) {
            return to_route('login');
        }

        if ($request->has('error')) {
            return $this->failed($intent, $invitation, __('Google sign-in was cancelled.'));
        }

        try {
            $googleUser = $this->googleSignIn->user();
        } catch (InvalidStateException) {
            return $this->failed($intent, $invitation, GoogleSignInFailed::generic()->getMessage());
        } catch (Exception $exception) {
            report($exception);

            return $this->failed($intent, $invitation, GoogleSignInFailed::generic()->getMessage());
        }

        if (! $googleUser instanceof GoogleUser || (string) $googleUser->getId() === '') {
            return $this->failed($intent, $invitation, GoogleSignInFailed::generic()->getMessage());
        }

        if ($user === null) {
            return $this->logIn($request, $googleUser, $invitation, $resolveGoogleUser);
        }

        return $intent === GoogleAuthIntent::Connect
            ? $this->connect($user, $googleUser)
            : $this->confirmPassword($request, $user, $googleUser);
    }

    /**
     * Log in as the account behind the Google identity, challenging for the
     * second factor exactly as Fortify's password login does.
     */
    private function logIn(Request $request, GoogleUser $googleUser, ?string $invitation, ResolveGoogleUser $resolveGoogleUser): Response
    {
        try {
            $user = $resolveGoogleUser->handle($googleUser, $invitation, (string) $request->ip());
        } catch (GoogleSignInFailed $exception) {
            return $this->failed(GoogleAuthIntent::Login, $invitation, $exception->getMessage());
        }

        if (Features::enabled(Features::twoFactorAuthentication()) && $user->hasEnabledTwoFactorAuthentication()) {
            $request->session()->put([
                'login.id' => $user->getKey(),
                'login.remember' => false,
            ]);

            TwoFactorAuthenticationChallenged::dispatch($user);

            return to_route('two-factor.login');
        }

        Auth::guard(config('fortify.guard'))->login($user);

        $request->session()->regenerate();

        return app(LoginResponse::class)->toResponse($request);
    }

    /**
     * Attach the Google identity to the signed-in account.
     */
    private function connect(User $user, GoogleUser $googleUser): RedirectResponse
    {
        $googleId = (string) $googleUser->getId();

        if ($user->google_id !== null && $user->google_id !== $googleId) {
            return $this->toSecuritySettings('error', __('A Google account is already connected.'));
        }

        if (User::query()->where('google_id', $googleId)->whereKeyNot($user->getKey())->exists()) {
            return $this->toSecuritySettings('error', __('That Google account is already connected to another Maildun account.'));
        }

        try {
            $user->forceFill(['google_id' => $googleId])->save();
        } catch (UniqueConstraintViolationException) {
            return $this->toSecuritySettings('error', __('That Google account is already connected to another Maildun account.'));
        }

        return $this->toSecuritySettings('success', __('Google account connected.'));
    }

    /**
     * Count choosing the connected Google account as confirming the password.
     */
    private function confirmPassword(Request $request, User $user, GoogleUser $googleUser): RedirectResponse
    {
        if ($user->google_id === null || ! hash_equals($user->google_id, (string) $googleUser->getId())) {
            return to_route('password.confirm')
                ->withErrors(['google' => __('Choose the Google account connected to your Maildun account.')]);
        }

        $request->session()->passwordConfirmed();

        return redirect()->intended(route('home'));
    }

    /**
     * Send the browser back to where the flow started, with the reason.
     */
    private function failed(GoogleAuthIntent $intent, ?string $invitation, string $message): RedirectResponse
    {
        return match ($intent) {
            GoogleAuthIntent::Login => to_route('login', array_filter(['invitation' => $invitation]))
                ->withErrors(['google' => $message]),
            GoogleAuthIntent::Confirm => to_route('password.confirm')
                ->withErrors(['google' => $message]),
            GoogleAuthIntent::Connect => $this->toSecuritySettings('error', $message),
        };
    }

    /**
     * @param  'success'|'error'  $type
     */
    private function toSecuritySettings(string $type, string $message): RedirectResponse
    {
        Inertia::flash('toast', ['type' => $type, 'message' => $message]);

        return to_route('security.edit');
    }
}
