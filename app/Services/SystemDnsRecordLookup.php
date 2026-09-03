<?php

namespace App\Services;

use App\Contracts\DnsRecordLookup;
use Throwable;

final class SystemDnsRecordLookup implements DnsRecordLookup
{
    /** @return list<array<string, mixed>> */
    public function lookup(string $hostname): array
    {
        try {
            $records = @dns_get_record($hostname, DNS_A | DNS_AAAA | DNS_CNAME | DNS_TXT);
        } catch (Throwable) {
            return [];
        }

        if (! is_array($records)) {
            return [];
        }

        return $records;
    }
}
