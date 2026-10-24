<?php

namespace App\Services;

use App\Contracts\MailDomainLookup;
use Illuminate\Support\Facades\Cache;

class SystemMailDomainLookup implements MailDomainLookup
{
    /** A domain that always publishes MX records, used to tell "no mail server" from "no DNS". */
    private const string PROBE_DOMAIN = 'gmail.com';

    /** @var list<int> */
    private const array RECORD_TYPES = [DNS_MX, DNS_A, DNS_AAAA];

    private ?bool $dnsAvailable = null;

    public function acceptsMail(string $domain): ?bool
    {
        $this->dnsAvailable ??= $this->hasMailRecords(self::PROBE_DOMAIN) === true;

        if (! $this->dnsAvailable) {
            return null;
        }

        $key = 'mail-domain:'.strtolower($domain);
        $cached = Cache::get($key);

        if (is_bool($cached)) {
            return $cached;
        }

        $acceptsMail = $this->hasMailRecords($domain);

        // A failed lookup is not an answer: caching it would mark a real
        // domain as unable to receive mail for a day.
        if ($acceptsMail !== null) {
            Cache::put($key, $acceptsMail, now()->addDay());
        }

        return $acceptsMail;
    }

    /**
     * True when the domain publishes an MX, A or AAAA record, false when the
     * resolver answered that it has none, and null when a lookup failed.
     */
    private function hasMailRecords(string $domain): ?bool
    {
        $domain = rtrim($domain, '.').'.';
        $lookupFailed = false;

        foreach (self::RECORD_TYPES as $type) {
            $records = $this->records($domain, $type);

            if ($records === false) {
                $lookupFailed = true;

                continue;
            }

            if ($records !== []) {
                return true;
            }
        }

        return $lookupFailed ? null : false;
    }

    /**
     * The resolver returns an empty list for a domain without such records and
     * false when the query itself failed (timeout, SERVFAIL).
     *
     * @return array<int, array<string, mixed>>|false
     */
    protected function records(string $domain, int $type): array|false
    {
        return @dns_get_record($domain, $type);
    }
}
