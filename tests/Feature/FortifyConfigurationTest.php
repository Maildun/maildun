<?php

/**
 * @return array<string, mixed>
 */
function loadFortifyConfigurationWithPasskeySecret(string $secret): array
{
    $key = 'PASSKEYS_USER_HANDLE_SECRET';
    $originalEnv = $_ENV;
    $originalServer = $_SERVER;
    $originalProcessValue = getenv($key);

    putenv("{$key}={$secret}");
    $_ENV[$key] = $secret;
    $_SERVER[$key] = $secret;

    try {
        return require config_path('fortify.php');
    } finally {
        putenv($originalProcessValue === false ? $key : "{$key}={$originalProcessValue}");
        $_ENV = $originalEnv;
        $_SERVER = $originalServer;
    }
}

test('an empty passkey user handle secret falls back to the application key', function () {
    config(['app.key' => 'base64:c2VjcmV0LWFwcGxpY2F0aW9uLWtleS1mb3ItdGVzdHM=']);

    $fortify = loadFortifyConfigurationWithPasskeySecret('');

    expect($fortify['passkeys']['user_handle_secret'])
        ->toBe('base64:c2VjcmV0LWFwcGxpY2F0aW9uLWtleS1mb3ItdGVzdHM=');
});

test('a configured passkey user handle secret is used instead of the application key', function () {
    config(['app.key' => 'base64:c2VjcmV0LWFwcGxpY2F0aW9uLWtleS1mb3ItdGVzdHM=']);

    $fortify = loadFortifyConfigurationWithPasskeySecret('dedicated-passkey-secret');

    expect($fortify['passkeys']['user_handle_secret'])->toBe('dedicated-passkey-secret');
});
