<?php

namespace App\Concerns;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;

/**
 * Stand-in for a current-password field on accounts that have no password.
 *
 * A Google-only account cannot type its current password, so sensitive
 * changes ask instead for a recent confirmation, which such an account makes
 * by choosing its connected Google account on the confirm-password screen.
 */
trait InteractsWithPasswordConfirmation
{
    /**
     * Determine whether the session confirmed the password recently enough,
     * using the same window as Illuminate\Auth\Middleware\RequirePassword.
     */
    protected function passwordRecentlyConfirmed(Request $request): bool
    {
        $secondsSinceConfirmation = Date::now()->unix() - (int) $request->session()->get('auth.password_confirmed_at', 0);

        return $secondsSinceConfirmation <= (int) config('auth.password_timeout', 10800);
    }

    /**
     * Send the user to confirm their identity, then back to the given page.
     */
    protected function redirectToPasswordConfirmation(string $returnTo): RedirectResponse
    {
        redirect()->setIntendedUrl($returnTo);

        return to_route('password.confirm');
    }
}
