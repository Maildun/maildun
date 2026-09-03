<?php

namespace App\Services;

use App\Contracts\DnsRecordLookup;
use App\Contracts\DnsResolver;

final class SystemDnsResolver implements DnsResolver
{
    private const int MAX_CNAME_DEPTH = 8;

    public function __construct(private readonly DnsRecordLookup $dnsRecordLookup) {}

    /** @return list<string> */
    public function resolve(string $hostname): array
    {
        return $this->resolveHostname($this->normalizeHostname($hostname), [], 0);
    }

    /**
     * @param  array<string, true>  $visited
     * @return list<string>
     */
    private function resolveHostname(string $hostname, array $visited, int $depth): array
    {
        if ($hostname === '' || $depth >= self::MAX_CNAME_DEPTH || isset($visited[$hostname])) {
            return [];
        }

        $visited[$hostname] = true;
        $records = $this->dnsRecordLookup->lookup($hostname);

        if ($records === []) {
            return [];
        }

        $addresses = [];
        $canonicalNames = [];

        foreach ($records as $record) {
            $type = strtoupper((string) ($record['type'] ?? ''));

            if ($type === 'A' || $type === 'AAAA') {
                $key = $type === 'A' ? 'ip' : 'ipv6';
                $address = $record[$key] ?? null;

                if (is_string($address) && $address !== '') {
                    $addresses[] = $address;
                }

                continue;
            }

            if ($type === 'CNAME') {
                $target = $record['target'] ?? null;

                if (! is_string($target) || ($target = $this->normalizeHostname($target)) === '') {
                    return [];
                }

                $canonicalNames[] = $target;
            }
        }

        foreach (array_unique($canonicalNames) as $canonicalName) {
            $canonicalAddresses = $this->resolveHostname($canonicalName, $visited, $depth + 1);

            if ($canonicalAddresses === []) {
                return [];
            }

            array_push($addresses, ...$canonicalAddresses);
        }

        return array_values(array_unique($addresses));
    }

    private function normalizeHostname(string $hostname): string
    {
        return strtolower(rtrim(trim($hostname), '.'));
    }
}
