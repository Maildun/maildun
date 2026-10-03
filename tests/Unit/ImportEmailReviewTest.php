<?php

use App\Contracts\MailDomainLookup;
use App\Services\ImportEmailReview;

function emailReview(): ImportEmailReview
{
    return new ImportEmailReview(new class implements MailDomainLookup
    {
        public function acceptsMail(string $domain): ?bool
        {
            return null;
        }
    });
}

test('common provider typos suggest the intended domain', function (string $domain, ?string $suggestion) {
    expect(emailReview()->suggestDomain($domain))->toBe($suggestion);
})->with([
    'swapped letters' => ['gmial.com', 'gmail.com'],
    'missing letter' => ['hotmal.com', 'hotmail.com'],
    'mistyped tld' => ['gmail.con', 'gmail.com'],
    'short tld' => ['yahoo.co', 'yahoo.com'],
    'known provider' => ['gmail.com', null],
    'known regional provider' => ['yahoo.co.uk', null],
    'company domain' => ['acme.com', null],
    'short unrelated domain' => ['mail.de', null],
]);

test('role and disposable addresses are recognised', function () {
    $review = emailReview();

    expect($review->isRoleAddress('Info@acme.com'))->toBeTrue()
        ->and($review->isRoleAddress('ada@acme.com'))->toBeFalse()
        ->and($review->isDisposable('mailinator.com'))->toBeTrue()
        ->and($review->isDisposable('acme.com'))->toBeFalse();
});
