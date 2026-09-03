<?php

use App\Contracts\DnsRecordLookup;
use App\Services\SystemDnsResolver;

/**
 * @param  array<string, list<array<string, mixed>>>  $recordsByHostname
 */
function dnsRecordLookupReturning(array $recordsByHostname): DnsRecordLookup
{
    return new class($recordsByHostname) implements DnsRecordLookup
    {
        /** @param array<string, list<array<string, mixed>>> $recordsByHostname */
        public function __construct(private readonly array $recordsByHostname) {}

        /** @return list<array<string, mixed>> */
        public function lookup(string $hostname): array
        {
            return $this->recordsByHostname[$hostname] ?? [];
        }
    };
}

test('the dns resolver follows cname chains to terminal address records', function () {
    $resolver = new SystemDnsResolver(dnsRecordLookupReturning([
        'smtp.example.com' => [
            ['type' => 'CNAME', 'target' => 'edge.example.net.'],
        ],
        'edge.example.net' => [
            ['type' => 'CNAME', 'target' => 'mail.example.net.'],
        ],
        'mail.example.net' => [
            ['type' => 'A', 'ip' => '8.8.8.8'],
            ['type' => 'AAAA', 'ipv6' => '2606:4700:4700::1111'],
        ],
    ]));

    expect($resolver->resolve('SMTP.EXAMPLE.COM.'))->toBe([
        '8.8.8.8',
        '2606:4700:4700::1111',
    ]);
});

test('the dns resolver rejects cname loops', function () {
    $resolver = new SystemDnsResolver(dnsRecordLookupReturning([
        'smtp.example.com' => [
            ['type' => 'CNAME', 'target' => 'edge.example.net.'],
        ],
        'edge.example.net' => [
            ['type' => 'CNAME', 'target' => 'smtp.example.com.'],
        ],
    ]));

    expect($resolver->resolve('smtp.example.com'))->toBe([]);
});

test('the dns resolver bounds cname recursion', function () {
    $records = [];

    for ($index = 0; $index < 8; $index++) {
        $records["mail{$index}.example.com"] = [
            ['type' => 'CNAME', 'target' => 'mail'.($index + 1).'.example.com.'],
        ];
    }

    $records['mail8.example.com'] = [
        ['type' => 'A', 'ip' => '8.8.8.8'],
    ];

    $resolver = new SystemDnsResolver(dnsRecordLookupReturning($records));

    expect($resolver->resolve('mail0.example.com'))->toBe([]);
});
