<?php

namespace App\Http\Middleware;

use App\Models\TeamInvitation;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Turns visitors away from Fortify's register routes on a closed instance.
 *
 * Registration is gated here rather than by dropping Features::registration()
 * so the routes keep existing: Wayfinder generates the frontend's route
 * helpers from them during the deploy build, and a missing "register" export
 * fails the build. Gating at request time also means REGISTRATION_ENABLED can
 * be flipped from a Forge or Laravel Cloud dashboard without rebuilding.
 */
class EnsureRegistrationIsOpen
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->routeIs('register', 'register.store')) {
            return $next($request);
        }

        if (config('fortify.registration_open') || $this->hasPendingInvitation($request)) {
            return $next($request);
        }

        $message = __('Registration is closed. Ask an administrator to invite you.');

        return $request->expectsJson()
            ? response()->json(['message' => $message], 403)
            : to_route('login')->with('status', $message);
    }

    /**
     * Determine whether the visitor was invited to a workspace.
     *
     * An invitation can only be accepted by a signed-in user, so closing
     * registration outright would strand everyone who has been invited. The
     * register form does not post the code back, so the GET is matched on the
     * code in the link and the POST on the address being signed up.
     */
    private function hasPendingInvitation(Request $request): bool
    {
        $code = $request->query('invitation');
        $email = $request->input('email');

        if (! is_string($code) && ! is_string($email)) {
            return false;
        }

        return TeamInvitation::query()
            ->when(is_string($code), fn ($query) => $query->where('code', $code))
            ->when(! is_string($code), fn ($query) => $query->where('email', $email))
            ->whereNull('accepted_at')
            ->where(fn ($query) => $query
                ->whereNull('expires_at')
                ->orWhere('expires_at', '>=', now()))
            ->exists();
    }
}
