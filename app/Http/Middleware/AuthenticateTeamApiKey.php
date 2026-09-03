<?php

namespace App\Http\Middleware;

use App\Models\TeamApiKey;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateTeamApiKey
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $apiKey = TeamApiKey::findToken($request->bearerToken());

        if (! $apiKey instanceof TeamApiKey || $apiKey->team->trashed()) {
            return response()->json([
                'message' => __('Unauthenticated.'),
            ], Response::HTTP_UNAUTHORIZED);
        }

        $apiKey->markAsUsed();
        $request->attributes->set('teamApiKey', $apiKey);
        $request->attributes->set('team', $apiKey->team);

        return $next($request);
    }
}
