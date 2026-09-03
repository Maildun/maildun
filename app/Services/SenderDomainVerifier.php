<?php

namespace App\Services;

use App\Contracts\DnsRecordLookup;
use App\Models\TeamSenderDomain;

final class SenderDomainVerifier
{
    public function __construct(private readonly DnsRecordLookup $dnsRecordLookup) {}

    public function hasVerificationRecord(TeamSenderDomain $senderDomain): bool
    {
        $expected = $senderDomain->dnsRecordValue();

        foreach ($this->dnsRecordLookup->lookup($senderDomain->dnsRecordName()) as $record) {
            if (strtoupper((string) ($record['type'] ?? '')) !== 'TXT') {
                continue;
            }

            foreach ($this->txtValues($record) as $value) {
                if (hash_equals($expected, trim($value, " \t\n\r\0\x0B\""))) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $record
     * @return list<string>
     */
    private function txtValues(array $record): array
    {
        $values = [];
        $text = $record['txt'] ?? null;

        if (is_string($text)) {
            $values[] = $text;
        }

        $entries = $record['entries'] ?? null;

        if (is_array($entries)) {
            $segments = array_values(array_filter($entries, is_string(...)));

            if ($segments !== []) {
                $values[] = implode('', $segments);
            }
        }

        return array_values(array_unique($values));
    }
}
