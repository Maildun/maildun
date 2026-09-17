<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Exceptions\InvalidSignatureException;
use Symfony\Component\HttpFoundation\Response;

class ValidateInboxSignature
{
    /**
     * Query keys email clients and scanners often append to inbox links.
     *
     * @var list<string>
     */
    private const array IGNORE_QUERY = [
        'fbclid',
        'gclid',
        'mc_cid',
        'mc_eid',
        '_ga',
        '_gl',
    ];

    /**
     * Accept a signed inbox link even when the request host or scheme does
     * not match the URL the queue worker signed with APP_URL.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $ignore = function (string $parameter): bool {
            return str_starts_with($parameter, 'utm_')
                || in_array($parameter, self::IGNORE_QUERY, true);
        };

        if ($request->hasValidSignatureWhileIgnoring($ignore, true)
            || $request->hasValidSignatureWhileIgnoring($ignore, false)
            || $this->matchesConfiguredAppUrl($request, $ignore)) {
            return $next($request);
        }

        throw new InvalidSignatureException;
    }

    /**
     * @param  Closure(string): bool  $ignore
     */
    private function matchesConfiguredAppUrl(Request $request, Closure $ignore): bool
    {
        $origin = rtrim((string) config('app.url'), '/');

        if ($origin === '') {
            return false;
        }

        $canonical = Request::create(
            $origin.$request->getPathInfo(),
            $request->getMethod(),
            $request->query->all(),
        );

        $rawQuery = $request->server->get('VAPOR_RAW_QUERY_STRING') ?? $request->server->get('QUERY_STRING');

        if (is_string($rawQuery) && $rawQuery !== '') {
            $canonical->server->set('QUERY_STRING', $rawQuery);

            if ($request->server->has('VAPOR_RAW_QUERY_STRING')) {
                $canonical->server->set('VAPOR_RAW_QUERY_STRING', $rawQuery);
            }
        }

        return $canonical->hasValidSignatureWhileIgnoring($ignore, true);
    }
}
