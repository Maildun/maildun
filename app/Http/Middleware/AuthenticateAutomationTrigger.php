<?php

namespace App\Http\Middleware;

use App\Enums\AutomationStatus;
use App\Enums\AutomationTrigger;
use App\Models\Automation;
use Closure;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateAutomationTrigger
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $automation = $request->route('automation');

        abort_unless(
            $automation instanceof Automation
                && $automation->status === AutomationStatus::Active
                && $automation->trigger === AutomationTrigger::Api,
            Response::HTTP_NOT_FOUND,
        );

        abort_unless(
            $this->hasValidToken($request, $automation),
            Response::HTTP_UNAUTHORIZED,
            __('Invalid automation trigger token.'),
        );

        return $next($request);
    }

    protected function hasValidToken(Request $request, Automation $automation): bool
    {
        try {
            $storedToken = $this->storedToken($automation);
        } catch (DecryptException) {
            return false;
        }

        $headerToken = $request->header('X-Automation-Token');
        $providedToken = is_string($headerToken) && $headerToken !== ''
            ? $headerToken
            : $request->bearerToken();

        return is_string($storedToken)
            && is_string($providedToken)
            && hash_equals($storedToken, $providedToken);
    }

    /** @throws DecryptException */
    protected function storedToken(Automation $automation): mixed
    {
        return $automation->getAttribute('trigger_token');
    }
}
