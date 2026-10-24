<?php

use App\Services\SystemMailDomainLookup;

/**
 * @param  array<string, array<int, list<array<string, mixed>>|false>>  $answers  Resolver answers per domain and record type.
 */
function mailDomainLookup(array $answers): SystemMailDomainLookup
{
    return new class($answers) extends SystemMailDomainLookup
    {
        /** @param array<string, array<int, list<array<string, mixed>>|false>> $answers */
        public function __construct(public array $answers) {}

        protected function records(string $domain, int $type): array|false
        {
            return $this->answers[$domain][$type] ?? [];
        }
    };
}

test('a domain the resolver says has no records cannot receive mail', function () {
    $lookup = mailDomainLookup(['gmail.com.' => [DNS_MX => [['target' => 'mx.gmail.com']]]]);

    expect($lookup->acceptsMail('no-mail.example'))->toBeFalse();
});

test('a failed lookup is unknown and is not remembered as unable to receive mail', function () {
    $lookup = mailDomainLookup([
        'gmail.com.' => [DNS_MX => [['target' => 'mx.gmail.com']]],
        'acme.example.' => [DNS_MX => false, DNS_A => false, DNS_AAAA => false],
    ]);

    expect($lookup->acceptsMail('acme.example'))->toBeNull();

    $lookup->answers['acme.example.'] = [DNS_MX => [['target' => 'mx.acme.example']]];

    expect($lookup->acceptsMail('acme.example'))->toBeTrue();
});
