<?php

namespace App\Services;

use App\Contracts\MailDomainLookup;
use Illuminate\Support\Facades\Cache;

final class SystemMailDomainLookup implements MailDomainLookup
{
    /** A domain that always publishes MX records, used to tell "no mail server" from "no DNS". */
    private const string PROBE_DOMAIN = 'gmail.com';

    private ?bool $dnsAvailable = null;

    public function acceptsMail(string $domain): ?bool
    {
        $this->dnsAvailable ??= $this->hasMailRecords(self::PROBE_DOMAIN);

        if (! $this->dnsAvailable) {
            return null;
        }

        return Cache::remember(
            'mail-domain:'.strtolower($domain),
            now()->addDay(),
            fn (): bool => $this->hasMailRecords($domain),
        );
    }

    private function hasMailRecords(string $domain): bool
    {
        $domain = rtrim($domain, '.').'.';

        return @checkdnsrr($domain, 'MX') || @checkdnsrr($domain, 'A') || @checkdnsrr($domain, 'AAAA');
    }
}
