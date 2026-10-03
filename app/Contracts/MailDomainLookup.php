<?php

namespace App\Contracts;

interface MailDomainLookup
{
    /**
     * Whether the domain publishes a mail server, or null when DNS cannot be reached.
     */
    public function acceptsMail(string $domain): ?bool;
}
