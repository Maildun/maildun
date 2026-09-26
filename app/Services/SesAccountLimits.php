<?php

namespace App\Services;

use App\Enums\EmailProvider;
use App\Models\TeamEmailIntegration;
use Aws\Exception\AwsException;
use Aws\SesV2\SesV2Client;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * The Amazon SES account's sending limits: the 24-hour quota, how much of it
 * is used, the per-second rate, and whether the account is still in the
 * sandbox. Cached briefly so pages do not call AWS on every load.
 */
class SesAccountLimits
{
    private const int CACHE_SECONDS = 600;

    /**
     * @return array{available: true, max_24_hour_send: int, sent_last_24_hours: int, remaining: int, max_send_rate: float, sandbox: bool}|array{available: false, reason: string}|null
     */
    public function fetch(TeamEmailIntegration $integration): ?array
    {
        if ($integration->provider !== EmailProvider::AmazonSes) {
            return null;
        }

        return Cache::remember(
            'ses-account-limits:'.$integration->id.':'.$integration->verification_version,
            self::CACHE_SECONDS,
            fn (): array => $this->load($integration),
        );
    }

    /**
     * @return array{available: true, max_24_hour_send: int, sent_last_24_hours: int, remaining: int, max_send_rate: float, sandbox: bool}|array{available: false, reason: string}
     */
    private function load(TeamEmailIntegration $integration): array
    {
        try {
            $account = $this->client($integration->settings)->getAccount();
        } catch (AwsException $exception) {
            // Never surface the AWS message: it echoes request context while
            // workspace credentials are in scope.
            return [
                'available' => false,
                'reason' => $exception->getAwsErrorCode() === 'AccessDeniedException'
                    ? __('Grant ses:GetAccount to these credentials to see the sending quota.')
                    : __('Amazon SES could not be reached to read the sending quota.'),
            ];
        } catch (Throwable) {
            return ['available' => false, 'reason' => __('Amazon SES could not be reached to read the sending quota.')];
        }

        $quota = (array) ($account->get('SendQuota') ?? []);
        $max = (int) ($quota['Max24HourSend'] ?? 0);
        $sent = (int) ($quota['SentLast24Hours'] ?? 0);

        return [
            'available' => true,
            'max_24_hour_send' => $max,
            'sent_last_24_hours' => $sent,
            'remaining' => max(0, $max - $sent),
            'max_send_rate' => (float) ($quota['MaxSendRate'] ?? 0),
            'sandbox' => $account->get('ProductionAccessEnabled') !== true,
        ];
    }

    /**
     * Overridden in tests to supply a client backed by an AWS MockHandler.
     *
     * @param  array<string, mixed>  $settings
     */
    protected function client(array $settings): SesV2Client
    {
        return new SesV2Client([
            'version' => 'latest',
            'region' => (string) ($settings['region'] ?? 'us-east-1'),
            'credentials' => [
                'key' => (string) ($settings['access_key_id'] ?? ''),
                'secret' => (string) ($settings['secret_access_key'] ?? ''),
            ],
        ]);
    }
}
