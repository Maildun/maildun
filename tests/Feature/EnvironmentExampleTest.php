<?php

use Illuminate\Support\Facades\File;

test('the environment example separates provider selectors from conditional credentials', function () {
    $contents = File::get(base_path('.env.example'));

    expect($contents)
        ->toContain('Platform mail — required for password resets and other system notifications')
        ->toContain('Choose exactly one transport: "smtp" or "ses"')
        ->toContain('SMTP only — required when MAIL_MAILER=smtp')
        ->toContain('Amazon SES only — required when MAIL_MAILER=ses')
        ->toContain('File storage — choose local or S3-compatible storage')
        ->toContain('Local storage needs')
        ->toContain('Required only when FILESYSTEM_DISK=s3')
        ->toContain('Choose "aws", "r2", or "custom"');

    expect(preg_match_all('/^MAIL_MAILER=/m', $contents))->toBe(1)
        ->and(preg_match_all('/^FILESYSTEM_DISK=/m', $contents))->toBe(1)
        ->and(preg_match_all('/^OBJECT_STORAGE_PROVIDER=/m', $contents))->toBe(1);

    $mailSelectorPosition = strpos($contents, 'MAIL_MAILER=smtp');
    $smtpSettingsPosition = strpos($contents, 'MAIL_HOST=127.0.0.1');
    $sesSettingsPosition = strpos($contents, 'MAIL_SES_KEY=');

    expect($mailSelectorPosition)->toBeInt()->toBeLessThan($smtpSettingsPosition);
    expect($smtpSettingsPosition)->toBeInt()->toBeLessThan($sesSettingsPosition);

    $storageSelectorPosition = strpos($contents, 'FILESYSTEM_DISK=local');
    $providerSelectorPosition = strpos($contents, 'OBJECT_STORAGE_PROVIDER=aws');
    $awsSettingsPosition = strpos($contents, 'AWS_ACCESS_KEY_ID=');
    $r2SettingsPosition = strpos($contents, 'R2_ACCESS_KEY_ID=');

    expect($storageSelectorPosition)->toBeInt()->toBeLessThan($providerSelectorPosition);
    expect($providerSelectorPosition)->toBeInt()->toBeLessThan($awsSettingsPosition);
    expect($awsSettingsPosition)->toBeInt()->toBeLessThan($r2SettingsPosition);
});
