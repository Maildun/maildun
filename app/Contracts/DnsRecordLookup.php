<?php

namespace App\Contracts;

interface DnsRecordLookup
{
    /** @return list<array<string, mixed>> */
    public function lookup(string $hostname): array;
}
