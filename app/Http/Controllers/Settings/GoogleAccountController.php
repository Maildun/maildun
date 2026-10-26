<?php

namespace App\Http\Controllers\Settings;

use App\Enums\GoogleAuthIntent;
use App\Http\Controllers\Controller;
use App\Services\GoogleSignIn;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Connect or disconnect the Google account used for "Continue with Google"
 * from Security settings. GoogleAuthController finishes the connection when
 * Google sends the browser back.
 */
class GoogleAccountController extends Controller
{
    public function __construct(private readonly GoogleSignIn $googleSignIn) {}

    /**
     * Send the signed-in user to Google to choose the account to connect.
     */
    public function connect(Request $request): Response
    {
        abort_unless($this->googleSignIn->isEnabled(), 404);

        if ($request->user()->google_id !== null) {
            Inertia::flash('toast', ['type' => 'info', 'message' => __('A Google account is already connected.')]);

            return to_route('security.edit');
        }

        return $this->googleSignIn->redirect($request, GoogleAuthIntent::Connect);
    }

    /**
     * Disconnect the Google account.
     *
     * Refused while the account has no password, which would leave it with no
     * way to log in.
     */
    public function destroy(Request $request): RedirectResponse
    {
        abort_unless($this->googleSignIn->isEnabled(), 404);

        $user = $request->user();

        if (! $user->hasPassword()) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('Set a password before disconnecting Google, or you will not be able to log in.')]);

            return to_route('security.edit');
        }

        $user->forceFill(['google_id' => null])->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Google account disconnected.')]);

        return to_route('security.edit');
    }
}
