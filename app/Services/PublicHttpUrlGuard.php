<?php

namespace App\Services;

use App\Contracts\DnsResolver;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\IpUtils;

final class PublicHttpUrlGuard
{
    /** @var list<string> */
    private const array UNSAFE_GLOBAL_RANGES = [
        '192.88.99.0/24',
        '224.0.0.0/4',
        '64:ff9b::/96',
        '64:ff9b:1::/48',
        '2002::/16',
        '3fff::/20',
        '5f00::/16',
        'fec0::/10',
        'ff00::/8',
    ];

    public function __construct(private readonly DnsResolver $dnsResolver) {}

    /** @return array{hostname: string, address: string, port: int} */
    public function ensurePublic(string $url): array
    {
        $parts = parse_url($url);
        $scheme = is_array($parts) ? ($parts['scheme'] ?? null) : null;
        $hostname = is_array($parts) ? ($parts['host'] ?? null) : null;
        $expectedPort = $scheme === 'https' ? 443 : 80;
        $port = is_array($parts) ? ($parts['port'] ?? $expectedPort) : null;

        if (! in_array($scheme, ['http', 'https'], true)
            || ! is_string($hostname)
            || $hostname === ''
            || $port !== $expectedPort
            || isset($parts['user'])
            || isset($parts['pass'])) {
            throw new InvalidArgumentException;
        }

        $addresses = $this->dnsResolver->resolve($hostname);

        if ($addresses === []) {
            throw new InvalidArgumentException;
        }

        foreach ($addresses as $address) {
            if (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_GLOBAL_RANGE) === false
                || IpUtils::checkIp($address, self::UNSAFE_GLOBAL_RANGES)) {
                throw new InvalidArgumentException;
            }
        }

        return [
            'hostname' => $hostname,
            'address' => $addresses[0],
            'port' => $expectedPort,
        ];
    }
}
