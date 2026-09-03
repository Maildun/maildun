<?php

use App\Actions\Emails\ClassifyEmailTrackingEvent;
use App\Enums\EmailTrackingClassification;

test('tracking clients are classified conservatively', function (
    ?string $userAgent,
    EmailTrackingClassification $classification,
    ?string $client,
    string $device,
) {
    $result = (new ClassifyEmailTrackingEvent)->handle($userAgent);

    expect($result['classification'])->toBe($classification)
        ->and($result['client_family'])->toBe($client)
        ->and($result['device_type'])->toBe($device)
        ->and($result['is_bot'])->toBe($classification === EmailTrackingClassification::Bot)
        ->and($result['is_proxy'])->toBe($classification === EmailTrackingClassification::PrivacyProxy);
})->with([
    'desktop Chrome' => [
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 Chrome/128.0.0.0 Safari/537.36',
        EmailTrackingClassification::Human,
        'Chrome',
        'desktop',
    ],
    'mobile Safari' => [
        'Mozilla/5.0 (iPhone; CPU iPhone OS 17_6 like Mac OS X) AppleWebKit/605.1.15 Version/17.6 Mobile/15E148 Safari/604.1',
        EmailTrackingClassification::Human,
        'Safari',
        'mobile',
    ],
    'Gmail image proxy' => [
        'Mozilla/5.0 (compatible) GoogleImageProxy',
        EmailTrackingClassification::PrivacyProxy,
        'Gmail image proxy',
        'server',
    ],
    'security scanner' => [
        'Proofpoint URL Defense Link Scanner',
        EmailTrackingClassification::Bot,
        'Proofpoint scanner',
        'server',
    ],
    'generic agent' => [
        'Mozilla/5.0',
        EmailTrackingClassification::Unknown,
        null,
        'unknown',
    ],
    'missing agent' => [
        null,
        EmailTrackingClassification::Unknown,
        null,
        'unknown',
    ],
]);
