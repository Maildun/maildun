<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Refuse to let other sites frame the application (clickjacking).
 *
 * A response that already states its own framing rules keeps them: the
 * hosted email copy, for instance, sends frame-ancestors 'none'. The policy
 * is added as a separate header rather than merged into an existing one, and
 * browsers enforce every policy they receive, so it can only narrow one.
 */
class PreventCrossOriginFraming
{
    /**
     * Pages built to be embedded on customer sites with an iframe.
     *
     * @var list<string>
     */
    private const array EMBEDDABLE_ROUTES = [
        'public.subscribe_forms.show',
    ];

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($request->routeIs(...self::EMBEDDABLE_ROUTES)) {
            return $response;
        }

        if (! $response->headers->has('X-Frame-Options')) {
            $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        }

        if (! $this->declaresFrameAncestors($response)) {
            $response->headers->set('Content-Security-Policy', "frame-ancestors 'self'", false);
        }

        return $response;
    }

    private function declaresFrameAncestors(Response $response): bool
    {
        foreach ($response->headers->all('Content-Security-Policy') as $policy) {
            if (is_string($policy) && stripos($policy, 'frame-ancestors') !== false) {
                return true;
            }
        }

        return false;
    }
}
