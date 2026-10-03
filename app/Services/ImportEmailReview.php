<?php

namespace App\Services;

use App\Contracts\MailDomainLookup;
use Illuminate\Support\Str;

/**
 * Local, rule-based checks for imported email addresses. Nothing leaves the server
 * apart from DNS lookups for the address's domain.
 */
class ImportEmailReview
{
    /** @var list<string> */
    public const array KNOWN_DOMAINS = [
        '163.com', 'aol.com', 'att.net', 'btinternet.com', 'comcast.net', 'fastmail.com',
        'free.fr', 'gmail.com', 'gmx.com', 'gmx.de', 'gmx.net', 'googlemail.com', 'hey.com',
        'hotmail.co.uk', 'hotmail.com', 'icloud.com', 'libero.it', 'live.com', 'mac.com',
        'mail.com', 'mail.ru', 'me.com', 'msn.com', 'orange.fr', 'outlook.com', 'proton.me',
        'protonmail.com', 'qq.com', 'sbcglobal.net', 't-online.de', 'verizon.net', 'web.de',
        'yahoo.co.id', 'yahoo.co.jp', 'yahoo.co.uk', 'yahoo.com', 'yandex.com', 'yandex.ru',
        'ymail.com', 'zoho.com',
    ];

    /** @var list<string> */
    public const array DISPOSABLE_DOMAINS = [
        '10minutemail.com', 'burnermail.io', 'dispostable.com', 'emailondeck.com',
        'fakeinbox.com', 'getnada.com', 'guerrillamail.com', 'guerrillamail.net',
        'maildrop.cc', 'mailinator.com', 'mailnesia.com', 'mintemail.com', 'moakt.com',
        'mohmal.com', 'sharklasers.com', 'tempail.com', 'temp-mail.org', 'tempmail.com',
        'throwawaymail.com', 'trashmail.com', 'yopmail.com',
    ];

    /** @var list<string> */
    public const array ROLE_LOCAL_PARTS = [
        'abuse', 'admin', 'billing', 'contact', 'help', 'hello', 'info', 'marketing',
        'no-reply', 'noreply', 'office', 'postmaster', 'sales', 'support', 'team', 'webmaster',
    ];

    /** @var array<string, string> */
    private const array TLD_TYPOS = [
        'cm' => 'com', 'cmo' => 'com', 'co' => 'com', 'comm' => 'com', 'con' => 'com',
        'coom' => 'com', 'cpm' => 'com', 'om' => 'com', 'vom' => 'com', 'xom' => 'com',
        'nte' => 'net', 'ne' => 'net', 'nett' => 'net', 'ogr' => 'org', 'or' => 'org',
    ];

    /** @var array<string, bool|null> */
    private array $acceptsMail = [];

    public function __construct(private MailDomainLookup $mailDomainLookup) {}

    public static function domainOf(string $email): string
    {
        return Str::lower(Str::afterLast($email, '@'));
    }

    /**
     * Suggest the provider domain an unknown domain was most likely meant to be.
     */
    public function suggestDomain(string $domain): ?string
    {
        if (in_array($domain, self::KNOWN_DOMAINS, true) || ! str_contains($domain, '.')) {
            return null;
        }

        $name = Str::beforeLast($domain, '.');
        $tld = Str::afterLast($domain, '.');

        if (isset(self::TLD_TYPOS[$tld]) && in_array($name.'.'.self::TLD_TYPOS[$tld], self::KNOWN_DOMAINS, true)) {
            return $name.'.'.self::TLD_TYPOS[$tld];
        }

        $threshold = strlen($domain) >= 9 ? 2 : 1;
        $best = null;
        $bestDistance = PHP_INT_MAX;
        $tied = false;

        foreach (self::KNOWN_DOMAINS as $known) {
            $distance = levenshtein($domain, $known);

            if ($distance < $bestDistance) {
                [$best, $bestDistance, $tied] = [$known, $distance, false];
            } elseif ($distance === $bestDistance) {
                $tied = true;
            }
        }

        return $bestDistance <= $threshold && ! $tied ? $best : null;
    }

    public function isDisposable(string $domain): bool
    {
        return in_array($domain, self::DISPOSABLE_DOMAINS, true);
    }

    public function isRoleAddress(string $email): bool
    {
        return in_array(Str::lower(Str::before($email, '@')), self::ROLE_LOCAL_PARTS, true);
    }

    /**
     * Whether the domain can receive mail, or null when DNS is unavailable.
     */
    public function acceptsMail(string $domain): ?bool
    {
        if (in_array($domain, self::KNOWN_DOMAINS, true)) {
            return true;
        }

        if (! array_key_exists($domain, $this->acceptsMail)) {
            $this->acceptsMail[$domain] = $this->mailDomainLookup->acceptsMail($domain);
        }

        return $this->acceptsMail[$domain];
    }
}
