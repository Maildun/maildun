<?php

use App\Contracts\DnsResolver;
use App\Exceptions\EmailTransportException;
use App\Services\PublicSmtpHostGuard;
use Tests\TestCase;

uses(TestCase::class);

/**
 * @param  list<string>  $addresses
 */
function smtpDnsResolverReturning(array $addresses): DnsResolver
{
    return new class($addresses) implements DnsResolver
    {
        /** @param list<string> $addresses */
        public function __construct(private readonly array $addresses) {}

        /** @return list<string> */
        public function resolve(string $hostname): array
        {
            return $this->addresses;
        }
    };
}

test('smtp hosts must resolve exclusively to public addresses', function (array $addresses) {
    $guard = new PublicSmtpHostGuard(smtpDnsResolverReturning($addresses));

    expect(fn () => $guard->ensurePublic('smtp.example.com'))
        ->toThrow(EmailTransportException::class);
})->with([
    'unresolved hostname' => [[]],
    'private IPv4 address' => [['10.20.30.40']],
    'private IPv6 address' => [['fd12:3456:789a::1']],
    'IPv4 loopback address' => [['127.0.0.1']],
    'IPv6 loopback address' => [['::1']],
    'IPv4 link-local address' => [['169.254.169.254']],
    'IPv6 link-local address' => [['fe80::1']],
    'deprecated IPv6 site-local address' => [['fec0::1']],
    'reserved IPv4 address' => [['192.0.2.10']],
    'reserved IPv6 address' => [['2001:db8::10']],
    'second IPv6 documentation range' => [['3fff::1']],
    'IPv6 segment-routing SID range' => [['5f00::1']],
    'carrier-grade NAT address' => [['100.64.0.1']],
    'deprecated IPv4 relay range' => [['192.88.99.1']],
    'IPv4 multicast address' => [['239.255.255.250']],
    'IPv6 multicast address' => [['ff02::1']],
    'NAT64 transition address' => [['64:ff9b::808:808']],
    'mixed public and private answers' => [['8.8.8.8', '10.0.0.5']],
    'invalid DNS answer' => [['not-an-ip-address']],
]);

test('smtp hosts may resolve to public IPv4 and IPv6 addresses', function () {
    $guard = new PublicSmtpHostGuard(smtpDnsResolverReturning([
        '8.8.8.8',
        '2606:4700:4700::1111',
    ]));

    expect($guard->ensurePublic('smtp.example.com'))->toBe([
        '8.8.8.8',
        '2606:4700:4700::1111',
    ]);
});

test('local SMTP opt-in permits only loopback hosts', function (string $hostname, string $address) {
    config()->set('mail.allow_local_smtp_hosts', true);
    $guard = new PublicSmtpHostGuard(smtpDnsResolverReturning([]));

    expect($guard->ensureAllowedSmtpHost($hostname))->toBe([$address]);
})->with([
    'localhost' => ['localhost', '127.0.0.1'],
    'IPv4 loopback' => ['127.0.0.1', '127.0.0.1'],
    'IPv6 loopback' => ['::1', '::1'],
]);

test('local SMTP opt-in does not permit private network hosts', function () {
    config()->set('mail.allow_local_smtp_hosts', true);
    $guard = new PublicSmtpHostGuard(smtpDnsResolverReturning(['10.20.30.40']));

    expect(fn () => $guard->ensureAllowedSmtpHost('smtp.example.com'))
        ->toThrow(EmailTransportException::class);
});
