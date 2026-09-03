<?php

namespace App\Services;

use App\Contracts\DnsResolver;
use App\Exceptions\EmailTransportException;
use Symfony\Component\HttpFoundation\IpUtils;

final class PublicSmtpHostGuard
{
    /** @var array<string, string> */
    private const array LOOPBACK_HOSTS = [
        'localhost' => '127.0.0.1',
        '127.0.0.1' => '127.0.0.1',
        '::1' => '::1',
    ];

    /**
     * Multicast and transition ranges that PHP may classify as globally
     * routable despite being unsafe SMTP connection targets.
     *
     * @var list<string>
     */
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

    public static function isPermittedSmtpHost(string $hostname): bool
    {
        $hostname = strtolower($hostname);

        return preg_match('/\A(?=.{1,253}\z)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}\z/i', $hostname) === 1
            || (self::allowsLoopbackHosts() && array_key_exists($hostname, self::LOOPBACK_HOSTS));
    }

    /** @return non-empty-list<string> */
    public function ensurePublic(string $hostname): array
    {
        $addresses = $this->dnsResolver->resolve($hostname);

        if ($addresses === []) {
            throw new EmailTransportException;
        }

        foreach ($addresses as $address) {
            if (! $this->isPublicAddress($address)) {
                throw new EmailTransportException;
            }
        }

        return $addresses;
    }

    /** @return non-empty-list<string> */
    public function ensureAllowedSmtpHost(string $hostname): array
    {
        $normalizedHostname = strtolower($hostname);

        if (self::allowsLoopbackHosts() && array_key_exists($normalizedHostname, self::LOOPBACK_HOSTS)) {
            return [self::LOOPBACK_HOSTS[$normalizedHostname]];
        }

        return $this->ensurePublic($hostname);
    }

    private static function allowsLoopbackHosts(): bool
    {
        return app()->environment(['local', 'testing'])
            && config('mail.allow_local_smtp_hosts') === true;
    }

    private function isPublicAddress(string $address): bool
    {
        return filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_GLOBAL_RANGE) !== false
            && ! IpUtils::checkIp($address, self::UNSAFE_GLOBAL_RANGES);
    }
}
