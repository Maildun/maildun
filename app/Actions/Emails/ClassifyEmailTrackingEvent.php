<?php

namespace App\Actions\Emails;

use App\Enums\EmailTrackingClassification;
use Illuminate\Support\Str;

class ClassifyEmailTrackingEvent
{
    /**
     * @return array{
     *     classification: EmailTrackingClassification,
     *     classification_reason: string,
     *     client_family: string|null,
     *     device_type: 'desktop'|'mobile'|'tablet'|'server'|'unknown',
     *     is_bot: bool,
     *     is_proxy: bool
     * }
     */
    public function handle(?string $userAgent): array
    {
        $agent = Str::of($userAgent ?? '')->trim()->lower()->toString();

        if ($agent === '') {
            return $this->result(
                EmailTrackingClassification::Unknown,
                'missing_user_agent',
            );
        }

        if ($client = $this->privacyProxy($agent)) {
            return $this->result(
                EmailTrackingClassification::PrivacyProxy,
                'recognized_privacy_proxy',
                $client,
                'server',
            );
        }

        if ($client = $this->automatedClient($agent)) {
            return $this->result(
                EmailTrackingClassification::Bot,
                'recognized_automation',
                $client,
                'server',
            );
        }

        if ($client = $this->humanClient($agent)) {
            return $this->result(
                EmailTrackingClassification::Human,
                'recognized_browser_or_mail_client',
                $client,
                $this->deviceType($agent),
            );
        }

        return $this->result(
            EmailTrackingClassification::Unknown,
            'unrecognized_user_agent',
        );
    }

    private function privacyProxy(string $agent): ?string
    {
        return match (true) {
            Str::contains($agent, ['googleimageproxy', 'gmail image proxy']) => 'Gmail image proxy',
            Str::contains($agent, ['yahoomailproxy', 'yahoo image proxy']) => 'Yahoo image proxy',
            Str::contains($agent, ['applemailproxy', 'mail privacy protection', 'apple private relay']) => 'Apple Mail Privacy Protection',
            Str::contains($agent, ['outlookimageproxy', 'outlook image proxy']) => 'Outlook image proxy',
            default => null,
        };
    }

    private function automatedClient(string $agent): ?string
    {
        return match (true) {
            Str::contains($agent, 'proofpoint') => 'Proofpoint scanner',
            Str::contains($agent, 'mimecast') => 'Mimecast scanner',
            Str::contains($agent, ['barracuda', 'linkprotect']) => 'Barracuda scanner',
            Str::contains($agent, ['safelinks', 'microsoft atp', 'defenderoffice365']) => 'Microsoft security scanner',
            Str::contains($agent, ['sophos', 'trendmicro', 'symantec', 'mcafee']) => 'Security scanner',
            preg_match('/(?:^|[^a-z])(bot|crawler|spider|scanner|headless|phantomjs|wget|curl)(?:[^a-z]|$)/', $agent) === 1 => 'Automated client',
            default => null,
        };
    }

    private function humanClient(string $agent): ?string
    {
        return match (true) {
            Str::contains($agent, ['thunderbird/', 'thunderbird ']) => 'Thunderbird',
            Str::contains($agent, ['microsoft outlook', 'outlook-ios', 'outlook-android']) => 'Outlook',
            Str::contains($agent, ['applemail/', 'mail/']) => 'Apple Mail',
            Str::contains($agent, ['edg/', 'edgios/', 'edga/']) => 'Edge',
            Str::contains($agent, ['firefox/', 'fxios/']) => 'Firefox',
            Str::contains($agent, ['chrome/', 'crios/']) => 'Chrome',
            Str::contains($agent, 'safari/') && Str::contains($agent, ['version/', 'mobile/']) => 'Safari',
            default => null,
        };
    }

    /** @return 'desktop'|'mobile'|'tablet'|'unknown' */
    private function deviceType(string $agent): string
    {
        return match (true) {
            Str::contains($agent, ['ipad', 'tablet', 'kindle', 'silk/']) => 'tablet',
            Str::contains($agent, ['iphone', 'ipod', 'android', 'mobile']) => 'mobile',
            Str::contains($agent, ['macintosh', 'windows', 'x11', 'linux']) => 'desktop',
            default => 'unknown',
        };
    }

    /**
     * @param  'desktop'|'mobile'|'tablet'|'server'|'unknown'  $deviceType
     * @return array{
     *     classification: EmailTrackingClassification,
     *     classification_reason: string,
     *     client_family: string|null,
     *     device_type: 'desktop'|'mobile'|'tablet'|'server'|'unknown',
     *     is_bot: bool,
     *     is_proxy: bool
     * }
     */
    private function result(
        EmailTrackingClassification $classification,
        string $reason,
        ?string $clientFamily = null,
        string $deviceType = 'unknown',
    ): array {
        return [
            'classification' => $classification,
            'classification_reason' => $reason,
            'client_family' => $clientFamily,
            'device_type' => $deviceType,
            'is_bot' => $classification === EmailTrackingClassification::Bot,
            'is_proxy' => $classification === EmailTrackingClassification::PrivacyProxy,
        ];
    }
}
