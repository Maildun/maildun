<?php

use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

function resolveClientIp(?string $trustedProxies): string
{
    config(['trustedproxy.proxies' => $trustedProxies]);

    $request = Request::create('/', 'GET', server: ['REMOTE_ADDR' => '10.0.0.1']);
    $request->headers->set('X-Forwarded-For', '203.0.113.7');

    $resolved = '';

    (new TrustProxies)->handle($request, function (Request $request) use (&$resolved): Response {
        $resolved = (string) $request->ip();

        return new Response;
    });

    return $resolved;
}

test('a trusted proxy hands through the real client address', function () {
    expect(resolveClientIp('*'))->toBe('203.0.113.7')
        ->and(resolveClientIp('10.0.0.1'))->toBe('203.0.113.7');
});

test('an untrusted forwarded header is ignored', function () {
    expect(resolveClientIp(null))->toBe('10.0.0.1');
});

test('the trusted proxy list is configurable', function () {
    expect(config()->has('trustedproxy.proxies'))->toBeTrue()
        ->and(config('trustedproxy.proxies'))->toBe(env('TRUSTED_PROXIES'));
});
